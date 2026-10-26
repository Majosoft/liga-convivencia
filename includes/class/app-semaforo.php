<?php
if (!defined('ABSPATH')) { exit; }

/**
 * App instalable SOLO del Semáforo (1.0), aparte de la app general del
 * profesorado: su propia dirección (por defecto https://sitio/semaforo/), nombre,
 * icono y color. Al abrirla, el panel enseña únicamente "Mis gomets" (las clases
 * del día de quien entra, según su horario), aunque esa persona tenga además
 * acceso completo. Todo lo demás (manifest, service worker, página) lo hereda de
 * LC_App.
 */
class LC_App_Semaforo extends LC_App {

    const OPT = 'lc_app_semaforo';

    const DEFAULTS = [
        'slug'         => 'semaforo',
        'nombre'       => 'Semáforo Convivencia',
        'nombre_corto' => 'Semáforo',
        'icono'        => 0,
    ];

    const COLOR = '#059669'; // --lc-color-success
    const MODO = 'semaforo';
    const CACHE_PREFIX = 'lc-sem-';
    const ICONOS = 'semaforo-icon';
    const DESCRIPCION = 'Gomets del Semáforo de la Liga Convivencia: cada profesor, sus clases del día.';
}
