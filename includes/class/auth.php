<?php
if (!defined('ABSPATH')) { exit; }

class LC_Auth {
    const OPT_KEY = 'lc_allowed_emails';          // textarea con emails permitidos
    const COOKIE  = 'lc_token';                  // cookie con el token
    const TOKEN_TTL = DAY_IN_SECONDS;            // validez del token

    public static function init() {
        // No necesitamos hooks específicos aquí, se manejan en las clases REST
    }

    /**
     * Verifica si un email está en la lista de permitidos
     */
    public static function is_email_allowed($email) {
        $list = array_filter(array_map('trim', preg_split('/\r?\n/', (string)get_option(self::OPT_KEY, ''))));
        $allowed = array_map('strtolower', array_map('sanitize_email', $list));
        return in_array(strtolower(sanitize_email($email)), $allowed, true);
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
        setcookie(self::COOKIE, $tok, $now + self::TOKEN_TTL, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
        return $tok;
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
     * Verifica si el usuario está autorizado
     */
    public static function only_allowed(\WP_REST_Request $req) {
        $tok = self::extract_token($req);
        $pl  = self::parse_token($tok);
        $email = $pl['e'] ?? '';
        $is_allowed = $pl && !empty($email) && self::is_email_allowed($email);
        
        // Log para debugging
        error_log("LC Auth Debug - Token: " . ($tok ? 'presente' : 'ausente') . 
                 ", Email: $email, Allowed: " . ($is_allowed ? 'sí' : 'no'));
        
        return $is_allowed;
    }
}
