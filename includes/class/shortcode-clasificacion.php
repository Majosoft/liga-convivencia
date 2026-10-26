<?php
/**
 * Shortcode para mostrar la clasificación
 */

if (!defined('ABSPATH')) { exit; }

class LC_Shortcode_Clasificacion {
    
    public static function register() {
        add_shortcode('liga_creus_clasificacion', [__CLASS__, 'render_clasificacion']);
        add_shortcode('liga_creus_test', [__CLASS__, 'render_test']);
    }
    
    public static function render_test($atts) {
        return '<div style="background: #f0f0f0; padding: 20px; border: 2px solid #333; margin: 10px;">
            <h3>✅ Shortcode funcionando correctamente</h3>
            <p>Plugin: Liga Convivencia v' . LC_VERSION . '</p>
            <p>URL: ' . LC_URL . '</p>
            <p>Timestamp: ' . current_time('mysql') . '</p>
        </div>';
    }
    
    public static function render_clasificacion($atts) {
        // Verificar que las constantes estén definidas
        if (!defined('LC_URL') || !defined('LC_VERSION')) {
            return '<p style="color: red;">Error: Plugin no inicializado correctamente</p>';
        }
        
        // Enqueue CSS y JS necesarios
        wp_enqueue_style('lc-tokens', LC_URL . 'assets/css/tokens.css', [], LC_VERSION);
        wp_enqueue_style('lc-clasificacion', LC_URL . 'assets/css/clasificacion.css', ['lc-tokens'], LC_VERSION);
        wp_enqueue_script('lc-helpers', LC_URL . 'assets/js/helpers.js', [], LC_VERSION, true);
        wp_enqueue_script('lc-clasificacion', LC_URL . 'assets/js/clasificacion.js', ['lc-helpers'], LC_VERSION, true);
        
        // helpers.js calcula las semanas a partir de window.LC_SETTINGS. Esta página
        // no carga assets.php, así que hay que darle los mismos datos de temporada
        // y trimestres; si no, su filtro "Semanal" ofrecería las semanas de otro
        // curso distinto a las que ofrece el panel de registro.
        wp_add_inline_script('lc-helpers', 'window.LC_SETTINGS = Object.assign(window.LC_SETTINGS || {}, '
            . wp_json_encode([
                'temporada'  => LC_Competiciones::get_temporada_vigente(),
                'trimestres' => LC_Competiciones::get_trimestres(),
            ]) . ');', 'before');

        // Configurar endpoint
        wp_localize_script('lc-clasificacion', 'LC_CLASIFICACION', [
            'endpoint' => rest_url('liga-creus/v1/clasificacion'),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
        
        return self::get_clasificacion_html();
    }
    
    private static function get_clasificacion_html() {
        return '
        <div id="lc-clasificacion-container">
            <div class="lc-loading">
                <div class="lc-spinner"></div>
                <p>Cargando clasificación...</p>
            </div>
            
            <div id="lc-clasificacion-content" style="display: none;">
                <div class="lc-toolbar lc-periodo-bar">
                    <div class="lc-field">
                        <label for="lc-periodo">Periodo</label>
                        <select id="lc-periodo">
                            <option value="general">General (temporada actual)</option>
                            <option value="semanal">Semanal</option>
                            <option value="mensual">Mensual</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="temporada">Temporada anterior</option>
                        </select>
                    </div>
                    <div class="lc-field" id="lc-periodo-semana-field" style="display:none">
                        <label for="lc-periodo-semana">Semana</label>
                        <select id="lc-periodo-semana"></select>
                    </div>
                    <div class="lc-field" id="lc-periodo-mes-field" style="display:none">
                        <label for="lc-periodo-mes">Mes</label>
                        <select id="lc-periodo-mes"></select>
                    </div>
                    <div class="lc-field" id="lc-periodo-trimestre-field" style="display:none">
                        <label for="lc-periodo-trimestre">Trimestre</label>
                        <select id="lc-periodo-trimestre"></select>
                    </div>
                    <div class="lc-field" id="lc-periodo-temporada-field" style="display:none">
                        <label for="lc-periodo-temporada">Temporada</label>
                        <select id="lc-periodo-temporada"></select>
                    </div>
                    <span id="lc-temporada-actual-label" class="lc-meta"></span>
                </div>

                <div class="lc-tabs" role="tablist">
                    <button class="lc-tab active" data-tab="general" role="tab" aria-selected="true">General</button>
                    <button class="lc-tab" data-tab="semaforo" role="tab" aria-selected="false">Semáforo</button>
                    <button class="lc-tab" data-tab="deportes" role="tab" aria-selected="false">Deportes</button>
                    <button class="lc-tab" data-tab="partes" role="tab" aria-selected="false">Partes</button>
                    <button class="lc-tab" data-tab="extraordinarias" role="tab" aria-selected="false">Extraordinarias</button>
                    <button class="lc-tab" data-tab="biblioteca" role="tab" aria-selected="false">Biblioteca</button>
                    <button class="lc-tab" data-tab="tiempo-aprendizaje" role="tab" aria-selected="false">T. Aprendizaje</button>
                    <button class="lc-tab" data-tab="alumnos" role="tab" aria-selected="false">Alumnos</button>
                </div>

                <div id="lc-tab-general" class="lc-tab-panel" role="tabpanel">
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th>Semáforo</th>
                                    <th>Deportes</th>
                                    <th>Partes</th>
                                    <th>Extraordinarias</th>
                                    <th>Biblioteca</th>
                                    <th>T. Aprendizaje</th>
                                    <th class="lc-total">Total</th>
                                </tr>
                            </thead>
                            <tbody id="lc-general-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="lc-tab-semaforo" class="lc-tab-panel" role="tabpanel" hidden>
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th>Puntos Semáforo</th>
                                </tr>
                            </thead>
                            <tbody id="lc-semaforo-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="lc-tab-deportes" class="lc-tab-panel" role="tabpanel" hidden>
                    <div class="lc-tabs lc-tabs-sub" id="lc-subtabs-deportes" role="tablist"></div>
                    <div id="lc-subpanels-deportes"></div>
                </div>

                <div id="lc-tab-partes" class="lc-tab-panel" role="tabpanel" hidden>
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th>Positivos</th>
                                    <th>Leves</th>
                                    <th>Graves</th>
                                    <th class="lc-total">Puntos Partes</th>
                                </tr>
                            </thead>
                            <tbody id="lc-partes-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="lc-tab-extraordinarias" class="lc-tab-panel" role="tabpanel" hidden>
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th class="lc-total">Puntos Extraordinarias</th>
                                </tr>
                            </thead>
                            <tbody id="lc-extraordinarias-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="lc-tab-biblioteca" class="lc-tab-panel" role="tabpanel" hidden>
                    <div class="lc-tabs lc-tabs-sub" id="lc-subtabs-biblioteca" role="tablist"></div>
                    <div id="lc-subpanels-biblioteca"></div>
                </div>

                <div id="lc-tab-tiempo-aprendizaje" class="lc-tab-panel" role="tabpanel" hidden>
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th class="lc-total">Puntos T. Aprendizaje</th>
                                </tr>
                            </thead>
                            <tbody id="lc-tiempo-aprendizaje-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="lc-tab-alumnos" class="lc-tab-panel" role="tabpanel" hidden>
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Alumno</th>
                                    <th>Clase</th>
                                    <th class="lc-total">Puntos</th>
                                </tr>
                            </thead>
                            <tbody id="lc-alumnos-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lc-footer">
                    <p>Última actualización: <span id="lc-timestamp"></span></p>
                </div>
            </div>

            <div id="lc-error" class="lc-alert lc-alert-danger" style="display: none;">
                <p>❌ Error al cargar la clasificación. Inténtalo de nuevo más tarde.</p>
            </div>
        </div>';
    }
}
