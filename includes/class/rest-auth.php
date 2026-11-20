<?php
if (!defined('ABSPATH')) exit;

/**
 * Login en dos pasos (ver LC_Auth::solicitar_codigo()):
 *   POST /auth/login  {email}          -> envía un código al email si tiene acceso
 *   POST /auth/verify {email, codigo}  -> canjea el código por la cookie de sesión
 */
class LC_REST_Auth {
  public static function register(){
    register_rest_route('liga-creus/v1','/auth/login',[
      'methods'=>'POST','callback'=>[__CLASS__,'login'],'permission_callback'=>'__return_true',
      'args'=>[
        'email'=>['required'=>true,'type'=>'string'],
      ]
    ]);
    register_rest_route('liga-creus/v1','/auth/verify',[
      'methods'=>'POST','callback'=>[__CLASS__,'verify'],'permission_callback'=>'__return_true',
      'args'=>[
        'email'=>['required'=>true,'type'=>'string'],
        'codigo'=>['required'=>true,'type'=>'string'],
      ]
    ]);
    register_rest_route('liga-creus/v1','/auth/me',[
      'methods'=>'GET','callback'=>[__CLASS__,'me'],'permission_callback'=>'__return_true',
    ]);
    register_rest_route('liga-creus/v1','/auth/logout',[
      'methods'=>'POST','callback'=>[__CLASS__,'logout'],'permission_callback'=>'__return_true',
    ]);
  }

  // Comprobaciones comunes a login y verify: HTTPS y límite por IP.
  private static function bloqueo(){
    if (LC_Auth::https_requerido()) {
      return new \WP_REST_Response(['ok'=>false,'error'=>'El acceso requiere una conexión segura (HTTPS).'],403);
    }
    if (!LC_Auth::ip_permitida()) {
      return new \WP_REST_Response(['ok'=>false,'error'=>'Demasiados intentos. Espera unos minutos y vuelve a probar.'],429);
    }
    return null;
  }

  public static function login(\WP_REST_Request $req){
    if ($b = self::bloqueo()) return $b;
    $email = sanitize_email($req->get_param('email'));
    if (!$email || !is_email($email)) return new \WP_REST_Response(['ok'=>false,'error'=>'Email no válido'],400);
    LC_Auth::solicitar_codigo($email);
    // Misma respuesta tenga o no acceso: no se revela quién está autorizado.
    return ['ok'=>true,'mensaje'=>'Si ese email tiene acceso, te acabamos de enviar un código. Revisa también la carpeta de spam.'];
  }

  public static function verify(\WP_REST_Request $req){
    if ($b = self::bloqueo()) return $b;
    $email = sanitize_email($req->get_param('email'));
    if (!LC_Auth::verificar_codigo($email, $req->get_param('codigo'))) {
      return new \WP_REST_Response(['ok'=>false,'error'=>'Código incorrecto o caducado.'],403);
    }
    LC_Auth::mint_token($email);
    return ['ok'=>true];
  }

  public static function me(\WP_REST_Request $req){
    // Igual que only_allowed(): un usuario dado de baja no debe ver el panel
    // (si no, cada petición fallaría y el aviso de sesión caducada se repetiría).
    $pl = LC_Auth::parse_token(LC_Auth::extract_token($req));
    $rol = $pl ? LC_Auth::rol($pl['e']) : null;
    if (!$rol) return new \WP_REST_Response(['ok'=>false],401);
    // rol 'semaforo': el panel solo enseña sus gomets. profesor: con quién se
    // corresponde en el horario (null si no da clase o no tiene email puesto).
    $prof = LC_Horario::profesor_por_email($pl['e']);
    return ['ok'=>true,'email'=>$pl['e'],'rol'=>$rol,
            'profesor'=>$prof ? ['iniciales'=>$prof['iniciales'],'nombre'=>$prof['nombre']] : null];
  }

  public static function logout(\WP_REST_Request $req){
    // Invalida también el token en sí (no solo la cookie de este navegador).
    $pl = LC_Auth::parse_token(LC_Auth::extract_token($req));
    if ($pl) LC_Auth::revocar_sesiones($pl['e']);
    LC_Auth::clear_cookie();
    return ['ok'=>true];
  }
}
