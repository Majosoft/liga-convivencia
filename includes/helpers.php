<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Funciones de utilidad para el plugin Liga Convivencia
 */
class LC_Helpers {
    
    /**
     * Email de quien hace la petición, para la columna created_by. El
     * profesorado entra con el token del panel, no con una cuenta de WordPress,
     * así que se mira primero el token: antes todo quedaba como 'externo'.
     */
    public static function get_current_user_email() {
        $tok = $_COOKIE[LC_Auth::COOKIE] ?? null;
        $pl = $tok ? LC_Auth::parse_token($tok) : null;
        if (!empty($pl['e'])) return $pl['e'];
        $u = wp_get_current_user();
        return $u && $u->exists() ? $u->user_email : 'externo';
    }
    
    /**
     * Sanitiza y valida un email
     */
    public static function sanitize_email($email) {
        return sanitize_email($email);
    }
    
    /**
     * Sanitiza texto de entrada
     */
    public static function sanitize_text($text) {
        return sanitize_text_field($text);
    }
    
    /**
     * Sanitiza textarea
     */
    public static function sanitize_textarea($text) {
        return sanitize_textarea_field($text);
    }
    
    /**
     * Valida si un email es válido
     */
    public static function is_valid_email($email) {
        return is_email($email);
    }
    
    /**
     * Obtiene la configuración del plugin
     */
    public static function get_plugin_config() {
        return [
            'version' => LC_VERSION,
            'path' => LC_PATH,
            'url' => LC_URL,
            'endpoint' => rest_url('liga-creus/v1'),
        ];
    }
    
    /**
     * Log de errores personalizado
     */
    public static function log_error($message, $context = []) {
        if (WP_DEBUG_LOG) {
            error_log('LC Plugin: ' . $message . ' ' . wp_json_encode($context));
        }
    }
    
    /**
     * Log de información
     */
    public static function log_info($message, $context = []) {
        if (WP_DEBUG_LOG) {
            error_log('LC Plugin INFO: ' . $message . ' ' . wp_json_encode($context));
        }
    }
}