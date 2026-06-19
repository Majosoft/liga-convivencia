<?php
if (!defined('ABSPATH')) exit;

class LC_REST_Auth {
  public static function register(){
    register_rest_route('liga-creus/v1','/auth/login',[
      'methods'=>'POST','callback'=>[__CLASS__,'login'],'permission_callback'=>'__return_true',
      'args'=>['email'=>['required'=>true,'type'=>'string']]
    ]);
    register_rest_route('liga-creus/v1','/auth/me',[
      'methods'=>'GET','callback'=>[__CLASS__,'me'],'permission_callback'=>'__return_true',
    ]);
    register_rest_route('liga-creus/v1','/auth/logout',[
      'methods'=>'POST','callback'=>[__CLASS__,'logout'],'permission_callback'=>'__return_true',
    ]);
    register_rest_route('liga-creus/v1','/test',[
      'methods'=>'GET','callback'=>[__CLASS__,'test'],'permission_callback'=>'__return_true',
    ]);
  }

  public static function login(\WP_REST_Request $req){
    $email = sanitize_email($req->get_param('email'));
    if (!$email || !is_email($email)) return new \WP_REST_Response(['ok'=>false,'error'=>'Email no válido'],400);
    if (!LC_Auth::is_email_allowed($email)) return new \WP_REST_Response(['ok'=>false,'error'=>'No autorizado'],403);
    $tok = LC_Auth::mint_token($email);
    return ['ok'=>true,'token'=>$tok,'email'=>$email];
  }

  public static function me(\WP_REST_Request $req){
    $pl = LC_Auth::parse_token(LC_Auth::extract_token($req));
    if (!$pl) return new \WP_REST_Response(['ok'=>false],401);
    return ['ok'=>true,'email'=>$pl['e']];
  }

  public static function logout(\WP_REST_Request $req){
    setcookie(LC_Auth::COOKIE, '', time()-3600, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
    return ['ok'=>true];
  }

  public static function test(\WP_REST_Request $req){
    return ['ok'=>true, 'message'=>'API funcionando', 'timestamp'=>current_time('mysql')];
  }
}
