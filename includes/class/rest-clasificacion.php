<?php
/**
 * REST API para clasificación
 */

class LC_REST_Clasificacion {
    
    public static function register() {
        register_rest_route('liga-creus/v1', '/clasificacion', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_clasificacion'],
            'permission_callback' => '__return_true', // Público, sin autenticación
        ]);
    }
    
    public static function get_clasificacion(\WP_REST_Request $req) {
        global $wpdb; $p = $wpdb->prefix;
        
        // Obtener todas las clases
        $clases = ['1ºA', '1ºB', '2ºA', '2ºB', '3ºA', '3ºB', '4ºA', '4ºB'];
        $clasificacion = [];
        
        foreach ($clases as $clase) {
            $clasificacion[$clase] = [
                'clase' => $clase,
                'semaforo' => 0,
                'deportes' => 0,
                'partes' => 0,
                'biblioteca' => 0,
                'total' => 0
            ];
        }
        
        // 1. SEMÁFORO: total de la ÚLTIMA semana registrada por clase.
        // No se puede usar MAX(total): el total puede bajar de una semana a otra
        // (amarillos/rojos restan puntos), así que el máximo histórico no es el total vigente.
        $semaforo_data = $wpdb->get_results("
            SELECT clase, iso_year, iso_week, total
            FROM {$p}lc_semaforo
            ORDER BY iso_year ASC, iso_week ASC
        ");

        foreach ($semaforo_data as $row) {
            if (isset($clasificacion[$row->clase])) {
                $clasificacion[$row->clase]['semaforo'] = intval($row->total);
            }
        }
        
        // 2. DEPORTES: Obtener puntos por clase (cada equipo recibe sus puntos individuales)
        $deportes_data = $wpdb->get_results("
            SELECT equipo_a, SUM(pts_a) as puntos_a
            FROM {$p}lc_deportes 
            GROUP BY equipo_a
        ");
        
        foreach ($deportes_data as $row) {
            if (isset($clasificacion[$row->equipo_a])) {
                $clasificacion[$row->equipo_a]['deportes'] += intval($row->puntos_a);
            }
        }
        
        // También obtener puntos del equipo B
        $deportes_data_b = $wpdb->get_results("
            SELECT equipo_b, SUM(pts_b) as puntos_b
            FROM {$p}lc_deportes 
            GROUP BY equipo_b
        ");
        
        foreach ($deportes_data_b as $row) {
            if (isset($clasificacion[$row->equipo_b])) {
                $clasificacion[$row->equipo_b]['deportes'] += intval($row->puntos_b);
            }
        }
        
        // 3. PARTES: Obtener puntos por clase
        $partes_data = $wpdb->get_results("
            SELECT clase, SUM(partes) as total_partes
            FROM {$p}lc_partes
            GROUP BY clase
        ");
        
        foreach ($partes_data as $row) {
            if (isset($clasificacion[$row->clase])) {
                $clasificacion[$row->clase]['partes'] = intval($row->total_partes);
            }
        }
        
        // 4. BIBLIOTECA: Obtener puntos por clase
        $biblioteca_data = $wpdb->get_results("
            SELECT 
                JSON_EXTRACT(participantes, '$[*].clase') as clases,
                SUM(puntos) as total_biblioteca
            FROM {$p}lc_biblioteca 
            GROUP BY JSON_EXTRACT(participantes, '$[*].clase')
        ");
        
        // Procesar datos de biblioteca (participantes pueden ser de diferentes clases)
        foreach ($biblioteca_data as $row) {
            $clases_participantes = json_decode($row->clases, true);
            if (is_array($clases_participantes)) {
                $puntos_por_clase = intval($row->total_biblioteca) / count($clases_participantes);
                foreach ($clases_participantes as $clase) {
                    if (isset($clasificacion[$clase])) {
                        $clasificacion[$clase]['biblioteca'] += $puntos_por_clase;
                    }
                }
            }
        }
        
        // Calcular totales
        foreach ($clasificacion as $clase => &$data) {
            $data['total'] = $data['semaforo'] + $data['deportes'] + $data['partes'] + $data['biblioteca'];
        }
        
        // Ordenar por total (descendente)
        uasort($clasificacion, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        
        return [
            'ok' => true,
            'clasificacion' => array_values($clasificacion),
            'timestamp' => current_time('mysql')
        ];
    }
}
