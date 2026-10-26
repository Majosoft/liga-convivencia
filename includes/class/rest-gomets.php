<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Gomets del Semáforo por hora de clase (1.0). Solo para profesores con
 * horario (LC_Auth::solo_profesor): cada uno ve y pone únicamente los de sus
 * propias clases. La lógica vive en LC_Horario.
 */
class LC_REST_Gomets {

    public static function register() {
        self::register_horario();
        register_rest_route('liga-creus/v1', '/gomets', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'dia'],
            'permission_callback' => ['LC_Auth', 'solo_profesor'],
            'args' => ['fecha' => ['required' => true, 'type' => 'string']],
        ]);
        register_rest_route('liga-creus/v1', '/gomets', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'poner'],
            'permission_callback' => ['LC_Auth', 'solo_profesor'],
            'args' => [
                'sesion_id' => ['required' => true, 'type' => 'integer'],
                'fecha'     => ['required' => true, 'type' => 'string'],
                'color'     => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public static function register_horario() {
        // Su horario de la semana entero, para comprobarlo (ver ui-gomets.js).
        register_rest_route('liga-creus/v1', '/gomets/horario', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'horario'],
            'permission_callback' => ['LC_Auth', 'solo_profesor'],
        ]);
    }

    public static function horario(\WP_REST_Request $req) {
        $prof = self::profesor($req);
        $sesiones = array_map(function($s) {
            return [
                'dia' => (int) $s['dia'],
                'hora' => (int) $s['hora'],
                'materia' => $s['materia'],
                'grupo' => $s['grupo'],
                'clases' => array_values(array_filter(explode(',', $s['clases']))),
                'gomet' => (bool) $s['cuenta'] && $s['clases'] !== '',
            ];
        }, LC_Horario::horario_semana($prof['iniciales']));
        return ['ok' => true, 'profesor' => ['iniciales' => $prof['iniciales'], 'nombre' => $prof['nombre']], 'sesiones' => $sesiones];
    }

    private static function profesor($req) {
        return LC_Horario::profesor_por_email(LC_Auth::email_de($req));
    }

    /**
     * Fecha válida para poner gomets: día lectivo (lunes a viernes), no futura y
     * de los últimos 12 meses. Devuelve la fecha normalizada o un error.
     */
    private static function validar_fecha($fecha) {
        $d = DateTime::createFromFormat('!Y-m-d', (string) $fecha, wp_timezone());
        if (!$d || $d->format('Y-m-d') !== $fecha) return 'Fecha no válida.';
        $hoy = new DateTime('today', wp_timezone());
        if ($d > $hoy) return 'No se pueden poner gomets de días que aún no han llegado.';
        if ($d < (clone $hoy)->modify('-1 year')) return 'Esa fecha es de hace más de un año.';
        if ((int) $d->format('N') > 5) return 'Ese día no hay clase.';
        return $fecha;
    }

    public static function dia(\WP_REST_Request $req) {
        $prof = self::profesor($req);
        $fecha = sanitize_text_field($req['fecha']);
        // Fin de semana o futuro: lista vacía con el motivo, no un error, para
        // que el panel pueda decirlo sin más.
        $valida = self::validar_fecha($fecha);
        if ($valida !== $fecha) return ['ok' => true, 'fecha' => $fecha, 'sesiones' => [], 'aviso' => $valida];

        $sesiones = array_map(function($s) {
            return [
                'id' => (int) $s['id'],
                'hora' => (int) $s['hora'],
                'materia' => $s['materia'],
                'grupo' => $s['grupo'],
                'clases' => array_values(array_filter(explode(',', $s['clases']))),
                'color' => $s['color'] ?: '',
            ];
        }, LC_Horario::sesiones_del_dia($prof['iniciales'], $fecha));
        return ['ok' => true, 'fecha' => $fecha, 'sesiones' => $sesiones];
    }

    public static function poner(\WP_REST_Request $req) {
        $prof = self::profesor($req);
        $fecha = sanitize_text_field($req['fecha']);
        $color = sanitize_key($req['color']);
        if ($color !== '' && !in_array($color, LC_Horario::COLORES, true)) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Color no válido.'], 400);
        }
        $valida = self::validar_fecha($fecha);
        if ($valida !== $fecha) return new \WP_REST_Response(['ok' => false, 'error' => $valida], 400);

        $res = LC_Horario::poner_gomet($prof['iniciales'], intval($req['sesion_id']), $fecha, $color);
        if ($res !== true) return new \WP_REST_Response(['ok' => false, 'error' => $res], 400);
        return ['ok' => true];
    }
}
