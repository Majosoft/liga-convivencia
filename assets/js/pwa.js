// App instalable (PWA) del profesorado: registra el service worker y ofrece
// instalar la app. Solo se carga en la página propia de la app (LC_App), no en
// el shortcode [lc_panel].
(function(){
  const cfg = window.LC_APP || {};

  if ('serviceWorker' in navigator && cfg.sw){
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(cfg.sw, { scope: cfg.scope })
        .catch(e => console.error('Service worker', e));
    });
  }

  // La app instalada se queda abierta en segundo plano días enteros, pero la
  // página lleva un nonce de la API que caduca. Al volver tras muchas horas se
  // recarga para que los guardados no empiecen a fallar.
  const RECARGAR_TRAS_MS = 6 * 60 * 60 * 1000;
  let ocultaDesde = 0;
  document.addEventListener('visibilitychange', () => {
    if (document.hidden){ ocultaDesde = Date.now(); return; }
    if (ocultaDesde && Date.now() - ocultaDesde > RECARGAR_TRAS_MS) window.location.reload();
  });

  // ===== Aviso de instalación =====
  const instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const aviso  = document.getElementById('lc-app-install');
  const texto  = document.getElementById('lc-app-install-text');
  const btn    = document.getElementById('lc-app-install-btn');
  const cerrar = document.getElementById('lc-app-install-close');
  if (instalada || !aviso) return;

  const CLAVE = 'lc_app_aviso_cerrado';
  try { if (localStorage.getItem(CLAVE)) return; } catch(e){}

  cerrar.addEventListener('click', () => {
    aviso.hidden = true;
    try { localStorage.setItem(CLAVE, '1'); } catch(e){}
  });

  // Chrome/Edge (Android y ordenador): el navegador avisa de que se puede
  // instalar y la instalación se lanza desde nuestro botón.
  let pendiente = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    pendiente = e;
    texto.textContent = 'Instala la app para abrirla desde un icono, como cualquier otra aplicación.';
    btn.hidden = false;
    aviso.hidden = false;
  });
  btn.addEventListener('click', async () => {
    if (!pendiente) return;
    pendiente.prompt();
    await pendiente.userChoice;
    pendiente = null;
    aviso.hidden = true;
  });
  window.addEventListener('appinstalled', () => { aviso.hidden = true; });

  // iPhone/iPad: Safari no permite lanzar la instalación desde la web, solo
  // explicar cómo hacerlo a mano.
  const ua = navigator.userAgent;
  const esIOS = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (esIOS){
    texto.textContent = 'Para instalar la app: pulsa el botón Compartir (el cuadrado con la flecha) y elige «Añadir a pantalla de inicio».';
    aviso.hidden = false;
  }
})();
