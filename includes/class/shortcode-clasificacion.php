<?php
/**
 * Shortcode para mostrar la clasificación
 */

class LC_Shortcode_Clasificacion {
    
    public static function register() {
        add_shortcode('liga_creus_clasificacion', [__CLASS__, 'render_clasificacion']);
        add_shortcode('liga_creus_test', [__CLASS__, 'render_test']);
    }
    
    public static function render_test($atts) {
        return '<div style="background: #f0f0f0; padding: 20px; border: 2px solid #333; margin: 10px;">
            <h3>✅ Shortcode funcionando correctamente</h3>
            <p>Plugin: Liga Creus v' . LC_VERSION . '</p>
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
        wp_enqueue_style('lc-clasificacion', LC_URL . 'assets/css/clasificacion.css', [], LC_VERSION);
        wp_enqueue_script('lc-clasificacion', LC_URL . 'assets/js/clasificacion.js', [], LC_VERSION, true);
        
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
                <h2 class="lc-title">🏆 Clasificación Liga Creus</h2>
                
                <div class="lc-tabs">
                    <button class="lc-tab active" data-tab="general">General</button>
                    <button class="lc-tab" data-tab="semaforo">Semáforo</button>
                    <button class="lc-tab" data-tab="deportes">Deportes</button>
                    <button class="lc-tab" data-tab="partes">Partes</button>
                    <button class="lc-tab" data-tab="biblioteca">Biblioteca</button>
                </div>
                
                <div id="lc-tab-general" class="lc-tab-content active">
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th>Semáforo</th>
                                    <th>Deportes</th>
                                    <th>Partes</th>
                                    <th>Biblioteca</th>
                                    <th class="lc-total">Total</th>
                                </tr>
                            </thead>
                            <tbody id="lc-general-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="lc-tab-semaforo" class="lc-tab-content">
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
                
                <div id="lc-tab-deportes" class="lc-tab-content">
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th>Puntos Deportes</th>
                                </tr>
                            </thead>
                            <tbody id="lc-deportes-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="lc-tab-partes" class="lc-tab-content">
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th>Puntos Partes</th>
                                </tr>
                            </thead>
                            <tbody id="lc-partes-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="lc-tab-biblioteca" class="lc-tab-content">
                    <div class="lc-table-container">
                        <table class="lc-table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Clase</th>
                                    <th>Puntos Biblioteca</th>
                                </tr>
                            </thead>
                            <tbody id="lc-biblioteca-tbody">
                                <!-- Se llenará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="lc-footer">
                    <p>Última actualización: <span id="lc-timestamp"></span></p>
                </div>
            </div>
            
            <div id="lc-error" class="lc-error" style="display: none;">
                <p>❌ Error al cargar la clasificación. Inténtalo de nuevo más tarde.</p>
            </div>
        </div>';
    }
}
