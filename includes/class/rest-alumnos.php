<?php
if (!defined('ABSPATH')) { exit; }

/**
 * API de alumnos para los formularios del panel: listar los de una clase y dar
 * de alta uno nuevo sin salir del formulario.
 */
class LC_REST_Alumnos {

    public static function register(){
        register_rest_route('liga-creus/v1', '/alumnos', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'list'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase' => ['required' => false, 'type' => 'string'],
            ],
        ]);

        register_rest_route('liga-creus/v1', '/alumnos', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'create'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'nombre' => ['required' => true, 'type' => 'string'],
                'clase'  => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    /** Sin parámetro "clase" devuelve todos: lo usa el ranking global de alumnos. */
    public static function list(\WP_REST_Request $req){
        $clase = sanitize_text_field((string) $req->get_param('clase'));
        $alumnos = $clase !== '' ? LC_Alumnos::de_clase($clase) : LC_Alumnos::todos(false);
        return ['ok' => true, 'alumnos' => $alumnos];
    }

    /**
     * Alta al vuelo. Si ya existe un alumno con ese nombre en esa clase devuelve
     * el que hay en vez de duplicarlo, así que el formulario puede llamar a esto
     * sin comprobar antes si existe.
     */
    public static function create(\WP_REST_Request $req){
        $nombre = (string) $req->get_param('nombre');
        $clase  = sanitize_text_field((string) $req->get_param('clase'));

        if (trim($nombre) === '' || $clase === '') {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Indica el nombre del alumno y su clase'], 400);
        }
        if (!LC_Alumnos::clase_valida($clase)) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Clase no válida'], 400);
        }

        $id = LC_Alumnos::find_or_create($nombre, $clase);
        if (!$id) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'No se pudo crear el alumno'], 500);
        }

        return ['ok' => true, 'alumno' => LC_Alumnos::get($id), 'alumnos' => LC_Alumnos::de_clase($clase)];
    }
}
