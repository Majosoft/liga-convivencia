<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Acceso y sesión del profesorado (sin cuentas de WordPress).
 *
 * - Dos listas: Formularios (acceso completo, lc_usuarios) y Semáforo
 *   (profesorado del horario con email, lc_profesores).
 * - Login en dos pasos con código de un solo uso enviado por email, con
 *   límites por email y por IP y sin revelar quién tiene acceso.
 * - Sesión: token HMAC en cookie HttpOnly/SameSite=Strict, con versión por
 *   usuario para que cerrar sesión lo revoque.
 *
 * Documentación: docs/modulos/acceso.md · Pruebas: tests/auth-test.php
 */
class LC_Auth {
    const OPT_KEY_LEGACY = 'lc_allowed_emails';  // lista antigua de emails, solo para migración
    const OPT_USERS = 'lc_usuarios';             // [{email, creado}]
    const COOKIE  = 'lc_token';                  // cookie con el token
    const TOKEN_TTL = DAY_IN_SECONDS;            // validez del token

    public static function init() {
        // No necesitamos hooks específicos aquí, se manejan en las clases REST
    }

    public static function get_users() {
        $users = get_option(self::OPT_USERS, []);
        return is_array($users) ? $users : [];
    }

    private static function save_users($users) {
        update_option(self::OPT_USERS, array_values($users));
    }

    public static function find_user($email) {
        $email = strtolower(sanitize_email($email));
        if (!$email) return null;
        foreach (self::get_users() as $u) {
            if (strtolower($u['email'] ?? '') === $email) return $u;
        }
        return null;
    }

    /**
     * Da de alta un email autorizado. Devuelve true o un mensaje de error.
     */
    public static function add_user($email) {
        $email = strtolower(sanitize_email($email));
        if (!$email || !is_email($email)) return 'Email no válido.';
        if (self::find_user($email)) return 'Ya existe un usuario con ese email.';
        $users = self::get_users();
        $users[] = ['email' => $email, 'creado' => current_time('mysql')];
        self::save_users($users);
        return true;
    }

    public static function delete_user($email) {
        $email = strtolower(sanitize_email($email));
        $users = array_values(array_filter(self::get_users(), function($u) use ($email) {
            return strtolower($u['email'] ?? '') !== $email;
        }));
        self::save_users($users);
        return true;
    }

    /**
     * Estar en una de las listas permite PEDIR un código; la sesión solo se
     * concede a quien demuestra que lee ese buzón (ver solicitar_codigo()).
     */
    public static function is_authorized($email) {
        return self::rol($email) !== null;
    }

    /**
     * Qué puede hacer un email (1.0):
     * - 'completo': está en Acceso > Usuarios. Todas las competiciones.
     * - 'semaforo': solo es profesor con email en Profesorado. Únicamente pone
     *   los gomets de sus clases (ver LC_REST_Gomets); el resto de rutas le
     *   responden 401 porque siguen protegidas con only_allowed().
     * - null: sin acceso.
     */
    public static function rol($email) {
        if (self::find_user($email) !== null) return 'completo';
        if (LC_Horario::profesor_por_email($email) !== null) return 'semaforo';
        return null;
    }

    /** Email de la sesión de la request, o '' si no hay una válida. */
    public static function email_de($req) {
        $pl = self::parse_token(self::extract_token($req));
        return $pl['e'] ?? '';
    }

    /**
     * Permiso de las rutas de gomets: cualquier email que sea profesor con
     * horario, tenga o no acceso completo.
     */
    public static function solo_profesor(\WP_REST_Request $req) {
        $email = self::email_de($req);
        return $email !== '' && LC_Horario::profesor_por_email($email) !== null;
    }

    // ------------------------------------------------------------------
    // Código de acceso de un solo uso, enviado por email.
    //
    // Antes bastaba con escribir un email autorizado para entrar, y los emails
    // del profesorado son fáciles de adivinar. Ahora el login va en dos pasos:
    // se pide un código (llega al buzón) y se canjea por la sesión.
    // - La respuesta al pedirlo es la misma exista o no el email: la API no
    //   sirve para averiguar quién tiene acceso.
    // - Se guarda solo el HMAC del código, caduca en 10 minutos, admite 5
    //   intentos y no se reenvía más de una vez por minuto.
    // - Límite por IP para pedir y canjear códigos (fuerza bruta).
    // ------------------------------------------------------------------
    const CODIGO_TTL = 10 * MINUTE_IN_SECONDS;
    const CODIGO_INTENTOS = 5;
    const CODIGOS_HORA = 5;                    // códigos por email y hora
    // Por IP el límite es holgado: en un centro todo el profesorado sale a
    // internet con la MISMA IP pública, y a primera hora entran muchos a la vez
    // (cada entrada son 2 peticiones). Lo que frena la fuerza bruta es el
    // límite por email: 5 códigos/hora x 5 intentos = 25 de 1.000.000 por hora.
    const IP_MAX = 150;                        // peticiones de login por IP...
    const IP_VENTANA = 15 * MINUTE_IN_SECONDS; // ...en esta ventana

    private static function clave_codigo($email) {
        return 'lc_otp_' . md5(strtolower($email));
    }

    private static function hash_codigo($email, $codigo) {
        return hash_hmac('sha256', strtolower($email) . '|' . $codigo, wp_salt('auth'));
    }

    /**
     * Cuenta una petición de login desde la IP actual. Devuelve false si ya
     * ha superado el límite de la ventana.
     */
    public static function ip_permitida() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $clave = 'lc_rl_' . md5($ip);
        $n = (int) get_transient($clave);
        if ($n >= self::IP_MAX) return false;
        set_transient($clave, $n + 1, self::IP_VENTANA);
        return true;
    }

    /**
     * Si el email tiene acceso, genera un código y lo envía. No dice nada
     * hacia fuera sobre si el email existe. Devuelve el código generado (o
     * null) solo para que el modo demo pueda enseñarlo; en producción ese
     * valor no sale nunca del servidor (ver LC_REST_Auth::login()).
     */
    public static function solicitar_codigo($email) {
        $email = strtolower(sanitize_email($email));
        if (!$email || !self::is_authorized($email)) return null;

        $clave = self::clave_codigo($email);
        $previo = get_transient($clave);
        if (is_array($previo) && ($previo['enviado'] ?? 0) > time() - MINUTE_IN_SECONDS) return null;

        // Como mucho CODIGOS_HORA códigos por email y hora.
        $clave_hora = 'lc_otp_n_' . md5($email);
        $emitidos = (int) get_transient($clave_hora);
        if ($emitidos >= self::CODIGOS_HORA) return null;
        set_transient($clave_hora, $emitidos + 1, HOUR_IN_SECONDS);

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        set_transient($clave, [
            'h'        => self::hash_codigo($email, $codigo),
            'exp'      => time() + self::CODIGO_TTL,
            'intentos' => 0,
            'enviado'  => time(),
        ], self::CODIGO_TTL);

        $sitio = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $ok = wp_mail(
            $email,
            "Tu código de acceso: {$codigo}",
            "Tu código para entrar en la Liga Convivencia ({$sitio}) es:\n\n"
            . "    {$codigo}\n\n"
            . "Caduca en 10 minutos. Si no lo has pedido tú, ignora este mensaje: nadie puede entrar sin él."
        );
        if (!$ok) error_log('[Liga Convivencia] No se pudo enviar el código de acceso (wp_mail falló).');
        return $codigo;
    }

    /**
     * Modo demostración (WordPress Playground, sin correo): el código se
     * muestra en pantalla. Solo con la constante LC_DEMO, que define el
     * blueprint de la demo; nunca está activa en una instalación real.
     */
    public static function modo_demo() {
        return defined('LC_DEMO') && LC_DEMO === true;
    }

    /** Canjea el código por una sesión. Devuelve true si es válido. */
    public static function verificar_codigo($email, $codigo) {
        $email = strtolower(sanitize_email($email));
        $codigo = preg_replace('/\D/', '', (string) $codigo);
        if (!$email || strlen($codigo) !== 6) return false;

        $clave = self::clave_codigo($email);
        $d = get_transient($clave);
        if (!is_array($d) || ($d['exp'] ?? 0) < time()) return false;

        if (!hash_equals($d['h'], self::hash_codigo($email, $codigo))) {
            $d['intentos'] = (int) $d['intentos'] + 1;
            if ($d['intentos'] >= self::CODIGO_INTENTOS) delete_transient($clave);
            else set_transient($clave, $d, max(1, $d['exp'] - time()));
            return false;
        }
        delete_transient($clave);
        // Se comprueba otra vez: pudieron darle de baja mientras tanto.
        return self::is_authorized($email);
    }

    /**
     * Fuera de un entorno local, sin HTTPS no se inicia sesión: el código y
     * la cookie viajarían en claro.
     */
    public static function https_requerido() {
        return !is_ssl() && !in_array(wp_get_environment_type(), ['local', 'development'], true);
    }

    // ------------------------------------------------------------------
    // Versión de sesión por email: va dentro del token y cerrar sesión la
    // incrementa, así que un token copiado deja de valer al hacer logout
    // (en todos los dispositivos de esa persona).
    // ------------------------------------------------------------------
    const OPT_SESIONES = 'lc_sesiones';

    private static function version_sesion($email) {
        $v = get_option(self::OPT_SESIONES, []);
        return (int) ($v[strtolower($email)] ?? 0);
    }

    public static function revocar_sesiones($email) {
        $email = strtolower($email);
        $v = get_option(self::OPT_SESIONES, []);
        if (!is_array($v)) $v = [];
        $v[$email] = (int) ($v[$email] ?? 0) + 1;
        update_option(self::OPT_SESIONES, $v, false);
    }

    /**
     * Genera el token de sesión firmado (HMAC) y lo escribe en la cookie.
     */
    public static function mint_token($email) {
        $now = time();
        $email = strtolower(sanitize_email($email));
        $payload = [ 'e' => $email, 't' => $now, 'v' => self::version_sesion($email) ];
        $data = wp_json_encode($payload);
        $mac  = hash_hmac('sha256', $data, wp_salt('auth'));
        $tok  = rtrim(strtr(base64_encode($data.'|'.$mac), '+/', '-_'), '=');
        self::set_cookie($tok, $now + self::TOKEN_TTL);
        return $tok;
    }

    /**
     * Escribe la cookie del token. SameSite=Strict es lo que corta el CSRF: los
     * endpoints de escritura autorizan solo con esta cookie, y WordPress responde
     * a cualquier Origin con Access-Control-Allow-Credentials: true, asi que sin
     * SameSite una web de terceros podria lanzar peticiones -- y leer la respuesta --
     * con la sesion de la victima. Strict basta porque el panel siempre llama al
     * REST desde el mismo dominio. (Requiere PHP 7.3+ por la forma con array.)
     */
    private static function set_cookie($value, $expires) {
        setcookie(self::COOKIE, $value, [
            'expires'  => $expires,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Borra la cookie del token (logout), con los mismos atributos con los que se
     * escribio -- si no coinciden, el navegador no la sustituye.
     */
    public static function clear_cookie() {
        self::set_cookie('', time() - 3600);
    }

    /**
     * Parsea y valida un token
     */
    public static function parse_token($tok) {
        if (!$tok) return null;
        $bin = base64_decode(strtr($tok, '-_', '+/'));
        if (!$bin || strpos($bin, '|') === false) return null;
        list($data, $mac) = explode('|', $bin, 2);
        $calc = hash_hmac('sha256', $data, wp_salt('auth'));
        if (!hash_equals($calc, $mac)) return null;
        $payload = json_decode($data, true);
        if (!is_array($payload)) return null;
        if (empty($payload['e']) || empty($payload['t'])) return null;
        if ((int)$payload['t'] + self::TOKEN_TTL < time()) return null; // expirado
        if ((int)($payload['v'] ?? 0) !== self::version_sesion($payload['e'])) return null; // sesión cerrada
        return $payload;
    }

    /**
     * Extrae el token de la request. Solo de la cookie HttpOnly: no se acepta
     * en cabecera Authorization, para que ningún script de la página pueda
     * leerlo ni reutilizarlo.
     */
    public static function extract_token(\WP_REST_Request $req) {
        if (!empty($_COOKIE[self::COOKIE])) return sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE]));
        return null;
    }

    /**
     * Verifica si el usuario está autorizado. Comprueba que el usuario siga
     * existiendo (no solo que el token sea válido): si el admin lo elimina,
     * los tokens ya emitidos para ese email dejan de aceptarse de inmediato.
     */
    public static function only_allowed(\WP_REST_Request $req) {
        $tok = self::extract_token($req);
        $pl  = self::parse_token($tok);
        $email = $pl['e'] ?? '';
        return $pl && !empty($email) && self::find_user($email) !== null;
    }
}
