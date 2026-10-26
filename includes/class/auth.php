<?php
if (!defined('ABSPATH')) { exit; }

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
     * El acceso lo concede estar en la lista de emails autorizados: no hay
     * segundo factor. Cualquiera que conozca uno de esos emails puede entrar,
     * así que la lista es la única barrera -- conviene mantenerla corta y dar de
     * baja a quien deje de necesitar acceso.
     */
    public static function is_authorized($email) {
        return self::find_user($email) !== null;
    }

    /**
     * Genera un token JWT para el email dado
     */
    public static function mint_token($email) {
        $now = time();
        $payload = [ 'e' => strtolower(sanitize_email($email)), 't' => $now ];
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
        return $payload;
    }

    /**
     * Extrae el token de la request (header Authorization o cookie)
     */
    public static function extract_token(\WP_REST_Request $req) {
        $hdr = $req->get_header('authorization');
        if ($hdr && preg_match('/Bearer\s+(.*)/i', $hdr, $m)) { return trim($m[1]); }
        if (!empty($_COOKIE[self::COOKIE])) return $_COOKIE[self::COOKIE];
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
