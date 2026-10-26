<?php
if (!defined('ABSPATH')) { exit; }

/**
 * App instalable para el profesorado (PWA).
 *
 * Sirve el mismo panel que el shortcode [lc_panel], pero en una dirección propia
 * (https://sitio/<slug>/), a pantalla completa y sin el tema de WordPress, con
 * el manifest y el service worker que hacen falta para que el navegador ofrezca
 * "Instalar app" / "Añadir a pantalla de inicio". Así cada profesor la abre desde
 * un icono como una app más, sin pasar por ninguna tienda.
 *
 * Las tres rutas (página, manifest y service worker) se resuelven en
 * parse_request comparando la ruta pedida con el slug configurado, en vez de con
 * reglas de reescritura: cambiar el enlace desde el admin funciona al momento,
 * sin tener que regenerar los enlaces permanentes.
 */
class LC_App {

    const OPT = 'lc_app';

    const DEFAULTS = [
        'slug'         => 'app-profesorado',
        'nombre'       => 'Liga Convivencia',
        'nombre_corto' => 'Liga',
        'icono'        => 0,
    ];

    // Rutas de WordPress que el slug no puede pisar.
    const SLUGS_RESERVADOS = ['wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login', 'feed', 'xmlrpc'];

    const COLOR = '#4F46E5'; // --lc-color-primary

    public static function init() {
        add_action('parse_request', [__CLASS__, 'maybe_serve'], 0);
    }

    public static function get() {
        $cfg = get_option(self::OPT, []);
        return array_merge(self::DEFAULTS, is_array($cfg) ? $cfg : []);
    }

    public static function slug() {
        return self::get()['slug'] ?: self::DEFAULTS['slug'];
    }

    /** Enlace público de la app, el que se reparte al profesorado. */
    public static function url($sub = '') {
        return home_url('/' . self::slug() . '/' . $sub);
    }

    public static function sanitize($in) {
        $in = is_array($in) ? $in : [];
        $slug = sanitize_title($in['slug'] ?? '');
        if ($slug === '' || in_array($slug, self::SLUGS_RESERVADOS, true)) {
            $slug = self::DEFAULTS['slug'];
        }
        $nombre = sanitize_text_field($in['nombre'] ?? '') ?: self::DEFAULTS['nombre'];
        $corto  = sanitize_text_field($in['nombre_corto'] ?? '') ?: $nombre;
        return [
            'slug'         => $slug,
            'nombre'       => $nombre,
            // Es el texto bajo el icono: más de ~12 caracteres se corta en el móvil.
            'nombre_corto' => mb_substr($corto, 0, 12),
            'icono'        => max(0, (int) ($in['icono'] ?? 0)),
        ];
    }

    /**
     * Ruta pedida relativa a la home de WordPress, sin barras en los extremos.
     * Tiene en cuenta que WordPress puede vivir en una subcarpeta (/web/...).
     */
    private static function ruta_pedida() {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $base = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($base !== '' && strpos($path, $base . '/') === 0) {
            $path = substr($path, strlen($base));
        }
        return trim(rawurldecode($path), '/');
    }

    public static function maybe_serve() {
        $slug = self::slug();
        $ruta = self::ruta_pedida();

        if ($ruta === $slug) {
            // Sin barra final la página quedaría fuera del scope del manifest
            // (/<slug>/) y el navegador no ofrecería instalarla.
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (substr($uri, -1) !== '/') {
                wp_safe_redirect(self::url(), 301);
                exit;
            }
            self::render_page();
        } elseif ($ruta === $slug . '/manifest.webmanifest') {
            self::render_manifest();
        } elseif ($ruta === $slug . '/sw.js') {
            self::render_sw();
        } else {
            return;
        }
        exit;
    }

    /**
     * Iconos del manifest: la imagen elegida en el admin si la hay (se declara
     * con su tamaño real) y si no los que vienen con el plugin.
     */
    private static function iconos() {
        $id = (int) self::get()['icono'];
        if ($id) {
            $src = wp_get_attachment_image_src($id, 'full');
            if ($src) {
                $tipo = get_post_mime_type($id) ?: 'image/png';
                return [
                    ['src' => $src[0], 'sizes' => $src[1] . 'x' . $src[2], 'type' => $tipo, 'purpose' => 'any'],
                ];
            }
        }
        $img = LC_URL . 'assets/img/';
        return [
            ['src' => $img . 'app-icon-192.png',          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $img . 'app-icon-512.png',          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $img . 'app-icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
    }

    /** Icono para iOS, que no lee el manifest y no admite transparencias. */
    private static function icono_apple() {
        $id = (int) self::get()['icono'];
        $src = $id ? wp_get_attachment_image_url($id, 'full') : '';
        return $src ?: LC_URL . 'assets/img/app-icon-apple-180.png';
    }

    private static function render_manifest() {
        $cfg = self::get();
        status_header(200);
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo wp_json_encode([
            'id'               => self::url(),
            'name'             => $cfg['nombre'],
            'short_name'       => $cfg['nombre_corto'],
            'description'      => 'Registro de la Liga Convivencia para el profesorado.',
            'lang'             => 'es',
            'start_url'        => self::url(),
            'scope'            => self::url(),
            'display'          => 'standalone',
            'orientation'      => 'any',
            'background_color' => '#F8FAFC',
            'theme_color'      => self::COLOR,
            // La carpeta del plugin lleva un espacio ("Plugin-Liga-Creus Def"):
            // en HTML lo codifica esc_url, pero en el JSON hay que hacerlo a mano.
            'icons'            => array_map(function($i) {
                $i['src'] = str_replace(' ', '%20', $i['src']);
                return $i;
            }, self::iconos()),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Service worker. Lo mínimo para que la app sea instalable y arranque rápido:
     * - CSS/JS/imágenes del plugin: primero la red y la caché solo de reserva. No
     *   se sirven de caché directamente porque la URL solo cambia al subir
     *   LC_VERSION: un JS editado sin subirla se quedaría viejo en la app.
     * - La página: siempre de la red. Lleva un nonce de la API que caduca, así
     *   que una copia guardada acabaría fallando; sin conexión se enseña un aviso.
     * - La API REST y cualquier petición que no sea GET: no se tocan. Los
     *   registros se guardan siempre directamente en el servidor.
     */
    private static function render_sw() {
        status_header(200);
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        $cache   = 'lc-app-' . LC_VERSION;
        $assets  = wp_json_encode(LC_URL);
        $nombre  = esc_html(self::get()['nombre']);
        $offline = '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $nombre . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;background:#F8FAFC;color:#0F172A;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px;text-align:center">'
            . '<div><h1 style="font-size:20px">Sin conexión</h1>'
            . '<p>Para registrar hace falta Internet. Comprueba la conexión y vuelve a intentarlo.</p>'
            . '<button onclick="location.reload()" style="font:inherit;padding:10px 20px;border:0;border-radius:8px;background:' . self::COLOR . ';color:#fff">Reintentar</button>'
            . '</div></body></html>';
        $offline = wp_json_encode($offline, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        echo <<<JS
const CACHE = '{$cache}';
const ASSETS = {$assets};
const OFFLINE = {$offline};

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (k.startsWith('lc-app-') && k !== CACHE) await caches.delete(k);
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() =>
      new Response(OFFLINE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })
    ));
    return;
  }

  if (req.url.startsWith(ASSETS)) {
    e.respondWith(caches.open(CACHE).then(async (c) => {
      try {
        const res = await fetch(req);
        if (res.ok) c.put(req, res.clone());
        return res;
      } catch (err) {
        const hit = await c.match(req);
        if (hit) return hit;
        throw err;
      }
    }));
  }
});
JS;
    }

    private static function render_page() {
        $cfg = self::get();

        // Nada de cachés de página: la página lleva el nonce de la API.
        nocache_headers();
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');

        LC_Assets::enqueue();
        wp_enqueue_script('lc-pwa', LC_URL . 'assets/js/pwa.js', [], LC_VERSION, true);
        wp_add_inline_script('lc-pwa', 'window.LC_APP = ' . wp_json_encode([
            'sw'    => self::url('sw.js'),
            'scope' => self::url(),
        ]) . ';', 'before');

        $nombre = $cfg['nombre'];
        ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($nombre); ?></title>
<meta name="theme-color" content="<?php echo esc_attr(self::COLOR); ?>">
<link rel="manifest" href="<?php echo esc_url(self::url('manifest.webmanifest')); ?>">
<link rel="icon" href="<?php echo esc_url(LC_URL . 'assets/img/app-icon-192.png'); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url(self::icono_apple()); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($cfg['nombre_corto']); ?>">
<?php wp_print_styles(['lc-tokens', 'lc-app']); ?>
<style>
  .lc-app-bar{
    display:flex; align-items:center; gap:var(--lc-space-3);
    padding:calc(var(--lc-space-3) + env(safe-area-inset-top)) var(--lc-space-4) var(--lc-space-3);
    background:var(--lc-color-primary); color:var(--lc-color-on-primary);
  }
  .lc-app-bar img{ width:32px; height:32px; border-radius:var(--lc-radius-sm); }
  .lc-app-bar h1{ font-size:20px; margin:0; color:inherit; }
  .lc-app-main{ max-width:1100px; margin:0 auto; padding:var(--lc-space-4) var(--lc-space-4) calc(var(--lc-space-5) + env(safe-area-inset-bottom)); }
  .lc-app-install{
    display:flex; flex-wrap:wrap; align-items:center; gap:var(--lc-space-3);
    margin-bottom:var(--lc-space-4); padding:var(--lc-space-3) var(--lc-space-4);
    background:var(--lc-color-primary-light); border-radius:var(--lc-radius-md);
    color:var(--lc-color-neutral-800); font-size:14px;
  }
  .lc-app-install[hidden]{ display:none; }
  @media (max-width: 480px){
    .lc-app-main{ padding-left:var(--lc-space-2); padding-right:var(--lc-space-2); }
  }
  .lc-app-install p{ margin:0; flex:1 1 220px; }
</style>
</head>
<body>
  <header class="lc-app-bar">
    <img src="<?php echo esc_url(self::icono_apple()); ?>" alt="">
    <h1><?php echo esc_html($nombre); ?></h1>
  </header>
  <main class="lc-app-main">
    <div class="lc-app-install" id="lc-app-install" hidden>
      <p id="lc-app-install-text"></p>
      <button type="button" class="lc-btn lc-btn-primary lc-btn-sm" id="lc-app-install-btn" hidden>Instalar app</button>
      <button type="button" class="lc-btn lc-btn-secondary lc-btn-sm" id="lc-app-install-close">Ahora no</button>
    </div>
    <div data-lc-panel></div>
  </main>
<?php wp_print_scripts(['lc-app', 'lc-pwa']); ?>
</body>
</html>
<?php
    }
}
