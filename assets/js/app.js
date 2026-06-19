// Variables globales
window.LC_EP = (window.LC_SETTINGS && LC_SETTINGS.endpoint) || (window.wpApiSettings && wpApiSettings.root + 'liga-creus/v1');
window.LC_NONCE = (window.LC_SETTINGS && LC_SETTINGS.nonce) || '';

// Funciones globales para API
window.LC_post = async function(url, body){
  const r = await fetch(url, {
    method:'POST',
    headers:{'Content-Type':'application/json','X-WP-Nonce': window.LC_NONCE},
    body: JSON.stringify(body||{})
  });
  let data = null;
  try { data = await r.json(); } catch(_) { data = {}; }
  if (!r.ok) throw new Error(data?.message || data?.error || `${r.status} ${r.statusText}`);
  return data;
};

window.LC_get = async function(url){
  const r = await fetch(url, { headers:{'X-WP-Nonce': window.LC_NONCE} });
  let data = {};
  try { data = await r.json(); } catch(_) { /* puede venir HTML o vacío */ }
  if (!r.ok) {
    return { ok:false, status:r.status, data };
  }
  return data; // normalmente tendrá { ok:true, ... }
};

(function(){
  const EP = window.LC_EP;
  const NONCE = window.LC_NONCE;

  // Auto-montaje en todos los contenedores del shortcode
  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('[data-lc-panel]')?.forEach(async (node)=>{ await render(node); });
  });

  async function render(root){
    try {
      const me = await window.LC_get(EP + '/auth/me');
      if (me && me.ok) {
        root.innerHTML = uiSelector(me.email);
        bindSelector(root);
      } else {
        root.innerHTML = uiLogin();
        bindLogin(root);
      }
    } catch (_) {
      root.innerHTML = uiLogin();
      bindLogin(root);
    }
  }

  function uiLogin(){
    return `
      <div class="lc-card">
        <h3 class="lc-title">Acceso para profesorado</h3>
        <div class="lc-field">
          <input type="email" id="lc-email" placeholder="tu@correo.edu" autocomplete="email"/>
          <button class="lc-btn primary" id="lc-go">Entrar</button>
        </div>
        <div id="lc-error" class="lc-alert" style="display:none"></div>
        <p class="lc-meta">* Solo correos previamente autorizados pueden iniciar sesión.</p>
      </div>`;
  }

  function uiSelector(email){
    return `
      <div class="lc-card">
        <h3 class="lc-title">Añadir resultados</h3>
        <div style="display: flex; justify-content:space-between">
          <div class="lc-meta">Sesión: <strong>${email}</strong></div>
          <div>
            <button class="lc-btn ghost" id="lc-logout">Cerrar sesión</button>
          </div>
        </div>
        <div style="margin-top:12px">
          <label for="lc-comp" class="lc-meta" style="display:block;margin-bottom:6px">Competición</label>
          <div class="lc-field" style="display:flex; justify-content: space-between;">
            <div>
              <select id="lc-comp">
                <option value="semaforo" selected>Semáforo</option>
                <option value="deportes">Deportes</option>
                <option value="partes">Partes</option>
                <option value="biblioteca">Biblioteca</option>
              </select>
            </div>
          </div>
        </div>
        <div id="lc-next" style="margin-top:4px"></div>
      </div>`;
  }

  function bindLogin(root){
    const btn = root.querySelector('#lc-go');
    const inp = root.querySelector('#lc-email');
    const err = root.querySelector('#lc-error');
    function showErr(msg){ err.textContent = msg; err.style.display = 'block'; }
    btn.addEventListener('click', async ()=>{
      err.style.display = 'none';
      const email = (inp.value||'').trim();
      if(!email){ return showErr('Introduce tu email.'); }
      const res = await window.LC_post(EP + '/auth/login', { email });
      if(!res.ok){ return showErr(res.error || 'No autorizado'); }
      try { localStorage.setItem('lc_token', res.token); } catch(e){}
      await render(root);
    });
    inp.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ btn.click(); } });
  }

  function bindSelector(root){
    // Logout
    root.querySelector('#lc-logout')?.addEventListener('click', async ()=>{
      await window.LC_post(EP + '/auth/logout', {});
      try { localStorage.removeItem('lc_token'); } catch(e){}
      await render(root);
    });

    const sel  = root.querySelector('#lc-comp');
    const next = root.querySelector('#lc-next');

  function renderComp(){
    next.innerHTML = '';
    switch (sel.value) {
      case 'semaforo':
        if (typeof uiSemaforo === 'function') {
          next.innerHTML = uiSemaforo();
          if (typeof bindSemaforo === 'function') bindSemaforo(next);
        } else {
          next.innerHTML = '<div class="lc-meta">Módulo Semáforo no disponible</div>';
        }
        break;
      case 'deportes':
        if (typeof uiDeportes === 'function') {
          next.innerHTML = uiDeportes();
          if (typeof bindDeportes === 'function') bindDeportes(next);
        } else {
          next.innerHTML = '<div class="lc-meta">Módulo Deportes no disponible</div>';
        }
        break;
        case 'partes':
          if (typeof uiPartes === 'function') {
            next.innerHTML = uiPartes();
            if (typeof bindPartes === 'function') bindPartes(next);
          } else {
            next.innerHTML = '<div class="lc-meta">Módulo Partes no disponible</div>';
          }
          break;
        case 'biblioteca':
          if (typeof uiBiblioteca === 'function') {
            next.innerHTML = uiBiblioteca();
            if (typeof bindBiblioteca === 'function') bindBiblioteca(next);
          } else {
            next.innerHTML = '<div class="lc-meta">Módulo Biblioteca no disponible</div>';
          }
          break;
        default:
          next.innerHTML = '<div class="lc-meta">Formulario en preparación.</div>';
          break;
    }
    next.scrollIntoView({ behavior:'smooth', block:'start' });
  }
    sel.addEventListener('change', renderComp);
    renderComp();
  }
})();