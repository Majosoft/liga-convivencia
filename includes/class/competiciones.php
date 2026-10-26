<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Configuración de deportes y actividades: qué existen, su modo de
 * puntuación y sus parámetros. Editable desde Ajustes > Liga Convivencia.
 *
 * No requiere tablas nuevas: lc_deportes/lc_biblioteca ya tienen las
 * columnas necesarias para cualquiera de los modos soportados.
 */
class LC_Competiciones {

    const OPT_DEPORTES = 'lc_config_deportes';
    const OPT_ACTIVIDADES = 'lc_config_actividades';
    const OPT_CLASES = 'lc_config_clases';
    const OPT_TRIMESTRES = 'lc_config_trimestres';
    const OPT_TEMPORADAS = 'lc_config_temporadas';
    const OPT_SEMAFORO = 'lc_config_semaforo';
    const OPT_PARTES = 'lc_config_partes';

    const DEFAULT_CLASES = [
        ['nombre' => '1ºA', 'activo' => true], ['nombre' => '1ºB', 'activo' => true],
        ['nombre' => '2ºA', 'activo' => true], ['nombre' => '2ºB', 'activo' => true],
        ['nombre' => '3ºA', 'activo' => true], ['nombre' => '3ºB', 'activo' => true],
        ['nombre' => '4ºA', 'activo' => true], ['nombre' => '4ºB', 'activo' => true],
    ];

    // Sin default sensato: las fechas de cada trimestre dependen del calendario
    // escolar de cada año, las define el admin desde Ajustes > Liga Convivencia.
    const DEFAULT_TRIMESTRES = [];

    // Igual que Trimestres: sin default, las fechas de cada temporada (curso
    // escolar) las define el admin al usar "Empezar nueva temporada".
    const DEFAULT_TEMPORADAS = [];

    // Valores CON SIGNO: el total es la suma de cantidad x puntos, sin restas
    // implícitas en el código. Así el admin decide si un gomet amarillo resta 1,
    // resta 2 o incluso no resta nada, sin tocar ninguna fórmula.
    // Los defaults son la puntuación que tenía el plugin cableada hasta ahora.
    const DEFAULT_SEMAFORO = [
        'verde'    => 1,
        'amarillo' => -1,
        'rojo'     => -3,
    ];

    const DEFAULT_PARTES = [
        'positivo' => 3,
        'leve'     => -1,
        'grave'    => -10,
    ];

    // Defaults = comportamiento actual antes de esta función, para que el
    // historial ya guardado siga puntuando exactamente igual.
    const DEFAULT_DEPORTES = [
        ['value' => 'futbol',     'label' => 'Fútbol',     'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'baloncesto', 'label' => 'Baloncesto', 'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'pinpon',     'label' => 'Pin Pon',    'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'matapollos', 'label' => 'Matapollos', 'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
    ];

    const DEFAULT_ACTIVIDADES = [
        ['value' => 'podcast',    'label' => 'Podcast',     'modo' => 'participantes', 'activo' => true, 'niveles' => [
            ['label' => 'Excelente', 'puntos' => 3], ['label' => 'Regular', 'puntos' => 1], ['label' => 'Insuficiente', 'puntos' => 0],
        ]],
        ['value' => 'traduccion', 'label' => 'Traducción',  'modo' => 'participantes', 'activo' => true, 'niveles' => [
            ['label' => 'Excelente', 'puntos' => 3], ['label' => 'Regular', 'puntos' => 1], ['label' => 'Insuficiente', 'puntos' => 0],
        ]],
        ['value' => 'mitos',      'label' => 'Mitos',       'modo' => 'participantes', 'activo' => true, 'niveles' => [
            ['label' => 'Excelente', 'puntos' => 3], ['label' => 'Regular', 'puntos' => 1], ['label' => 'Insuficiente', 'puntos' => 0],
        ]],
        ['value' => 'ajedrez',    'label' => 'Ajedrez',     'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
    ];

    // array_merge con el default: si la opción guardada no trae alguna clave
    // (config antigua, o una clave nueva añadida en una versión posterior) se
    // rellena con su valor de siempre en vez de quedar en 0 y romper el cálculo.
    private static function get_puntos($opt, $defaults) {
        $cfg = get_option($opt, $defaults);
        if (!is_array($cfg)) return $defaults;
        $out = array_merge($defaults, $cfg);
        return array_map('intval', array_intersect_key($out, $defaults));
    }

    public static function get_semaforo() {
        return self::get_puntos(self::OPT_SEMAFORO, self::DEFAULT_SEMAFORO);
    }

    public static function get_partes() {
        return self::get_puntos(self::OPT_PARTES, self::DEFAULT_PARTES);
    }

    public static function get_deportes() {
        $list = get_option(self::OPT_DEPORTES, self::DEFAULT_DEPORTES);
        return is_array($list) && !empty($list) ? $list : self::DEFAULT_DEPORTES;
    }

    public static function get_actividades() {
        $list = get_option(self::OPT_ACTIVIDADES, self::DEFAULT_ACTIVIDADES);
        return is_array($list) && !empty($list) ? $list : self::DEFAULT_ACTIVIDADES;
    }

    // Solo los activos: lo que se ofrece para registrar NUEVOS resultados en el panel.
    // Los inactivos siguen contando en la Clasificación (su histórico no desaparece).
    public static function get_deportes_activos() {
        return array_values(array_filter(self::get_deportes(), function($d){ return $d['activo'] ?? true; }));
    }

    public static function get_actividades_activas() {
        return array_values(array_filter(self::get_actividades(), function($a){ return $a['activo'] ?? true; }));
    }

    public static function get_deporte($value) {
        foreach (self::get_deportes() as $d) {
            if ($d['value'] === $value) return $d;
        }
        return null;
    }

    public static function get_actividad($value) {
        foreach (self::get_actividades() as $a) {
            if ($a['value'] === $value) return $a;
        }
        return null;
    }

    // Forma completa {nombre, activo, escudo}, con compatibilidad hacia atrás:
    // - formato antiguo (array de strings, antes de la función "Nueva temporada").
    // - escudo asignado antes de fusionarse con Clases (vivía en la opción suelta
    //   "lc_config_escudos", keyed por nombre de clase) — se usa como fallback si
    //   la fila de la clase todavía no tiene su propio campo "escudo".
    public static function get_clases_config() {
        $list = get_option(self::OPT_CLASES, self::DEFAULT_CLASES);
        if (!is_array($list) || empty($list)) $list = self::DEFAULT_CLASES;
        $escudos_legado = get_option('lc_config_escudos', []);
        return array_map(function($c) use ($escudos_legado) {
            $c = is_array($c) ? $c : ['nombre' => (string) $c, 'activo' => true];
            if (!isset($c['escudo'])) {
                $nombre = $c['nombre'] ?? '';
                $c['escudo'] = (is_array($escudos_legado) && isset($escudos_legado[$nombre])) ? (int) $escudos_legado[$nombre] : 0;
            }
            return $c;
        }, $list);
    }

    // Clases/grupos de la competición, editables desde Ajustes > Liga Convivencia.
    // TODAS (activas o no): la Clasificación necesita seguir mostrando el histórico
    // de una clase aunque ya no esté activa, igual que con Deportes/Actividades.
    public static function get_clases() {
        return array_map(function($c) { return $c['nombre']; }, self::get_clases_config());
    }

    // Solo las activas: lo que se ofrece para registrar NUEVOS resultados en el panel.
    public static function get_clases_activas() {
        return array_values(array_map(
            function($c) { return $c['nombre']; },
            array_filter(self::get_clases_config(), function($c) { return $c['activo'] ?? true; })
        ));
    }

    // Trimestres (periodos de fechas) para filtrar la Clasificación, editables desde Ajustes > Liga Convivencia.
    public static function get_trimestres() {
        $list = get_option(self::OPT_TRIMESTRES, self::DEFAULT_TRIMESTRES);
        return is_array($list) ? $list : self::DEFAULT_TRIMESTRES;
    }

    // Temporadas (cursos escolares), editables desde Ajustes > Liga Convivencia > Temporadas
    // o mediante el asistente "Empezar nueva temporada".
    public static function get_temporadas() {
        $list = get_option(self::OPT_TEMPORADAS, self::DEFAULT_TEMPORADAS);
        return is_array($list) ? $list : self::DEFAULT_TEMPORADAS;
    }

    // La temporada sin "fin" es la que está en curso. Si no hay ninguna (instalación
    // que no usa esta función, o admin que cerró todas a mano), no hay temporada activa
    // y la Clasificación "General" sigue siendo todo el histórico, sin filtrar.
    public static function get_temporada_activa() {
        foreach (self::get_temporadas() as $t) {
            if (empty($t['fin'])) return $t;
        }
        return null;
    }

    // Temporada que rige AHORA, para el selector de semanas/meses del panel.
    // Se prefiere la que contiene la fecha de hoy; si ninguna la contiene, la que
    // sigue en curso (sin fin); y si tampoco, la más reciente por fecha de inicio.
    // Sin temporadas configuradas devuelve null y el panel usa su regla de
    // siempre (octubre->junio), así que nada cambia para quien no las use.
    public static function get_temporada_vigente() {
        $temporadas = self::get_temporadas();
        if (empty($temporadas)) return null;

        $hoy = current_time('Y-m-d');
        foreach ($temporadas as $t) {
            $inicio = $t['inicio'] ?? '';
            $fin    = $t['fin'] ?? '';
            if ($inicio === '' || $inicio > $hoy) continue;
            if ($fin === '' || $fin >= $hoy) return $t;
        }

        $activa = self::get_temporada_activa();
        if ($activa) return $activa;

        usort($temporadas, function($a, $b) {
            return strcmp($b['inicio'] ?? '', $a['inicio'] ?? '');
        });
        return $temporadas[0];
    }

    // Escudo: un attachment ID de WordPress por clase, asignado en la propia
    // pestaña Clases (campo "escudo" de cada fila, ver get_clases_config()).
    public static function get_escudo($clase) {
        foreach (self::get_clases_config() as $c) {
            if (($c['nombre'] ?? '') === $clase) return (int) ($c['escudo'] ?? 0);
        }
        return 0;
    }

    public static function get_escudo_url($clase) {
        $id = self::get_escudo($clase);
        if (!$id) return '';
        $url = wp_get_attachment_image_url($id, 'medium');
        return $url ?: '';
    }
}
