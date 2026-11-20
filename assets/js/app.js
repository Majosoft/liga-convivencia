// Variables globales
window.LC_EP = (window.LC_SETTINGS && LC_SETTINGS.endpoint) || (window.wpApiSettings && wpApiSettings.root + 'liga-creus/v1');
window.LC_NONCE = (window.LC_SETTINGS && LC_SETTINGS.nonce) || '';
// Versiones anteriores guardaban el token aquí; ahora solo va en cookie HttpOnly.
try { localStorage.removeItem('lc_token'); } catch(e){}

// Sesión caducada (el token dura un día) o usuario dado de baja: WordPress
// responde rest_forbidden. Sin esto el panel seguía abierto mostrando los
// formularios vacíos, como si no hubiera nada registrado esa semana.
function lcSesionCaducada(url, r, data){
  if ((r.status !== 401 && r.status !== 403) || data?.code !== 'rest_forbidden') return false;
  if (url.indexOf('/auth/') !== -1 || window.LC_SESION_CADUCADA) return false;
  window.LC_SESION_CADUCADA = true;
  window.alert('Tu sesión ha caducado. Vuelve a entrar con tu email.');
  window.location.reload();
  return true;
}

// Funciones globales para API
window.LC_post = async function(url, body){
  const r = await fetch(url, {
    method:'POST',
    headers:{'Content-Type':'application/json','X-WP-Nonce': window.LC_NONCE},
    body: JSON.stringify(body||{})
  });
  let data = null;
  try { data = await r.json(); } catch(_) { data = {}; }
  lcSesionCaducada(url, r, data);
  if (!r.ok) throw new Error(data?.message || data?.error || `${r.status} ${r.statusText}`);
  return data;
};

// Borrado: mismos criterios que LC_post (lanza con el mensaje del servidor).
window.LC_delete = async function(url, body){
  const r = await fetch(url, {
    method:'DELETE',
    headers:{'Content-Type':'application/json','X-WP-Nonce': window.LC_NONCE},
    body: JSON.stringify(body||{})
  });
  let data = null;
  try { data = await r.json(); } catch(_) { data = {}; }
  lcSesionCaducada(url, r, data);
  if (!r.ok) throw new Error(data?.message || data?.error || `${r.status} ${r.statusText}`);
  return data;
};

window.LC_get = async function(url){
  const r = await fetch(url, { headers:{'X-WP-Nonce': window.LC_NONCE} });
  let data = {};
  try { data = await r.json(); } catch(_) { /* puede venir HTML o vacío */ }
  if (!r.ok) {
    lcSesionCaducada(url, r, data);
    return { ok:false, status:r.status, data };
  }
  return data; // normalmente tendrá { ok:true, ... }
};

(function(){
  const EP = window.LC_EP;

  // Pestañas -> módulo (Semáforo/Partes comparten "Clase" en la barra de contexto;
  // Deportes/Biblioteca gestionan sus propias clases por partido/actividad).
  // needsWeek: false = el módulo no usa la semana de la barra de contexto.
  const MODULES = {
    // 1.0: gomets por hora de clase del propio profesor (ui-gomets.js).
    gomets:           { ui: () => uiGomets(),           bind: (el, ctx) => bindGomets(el, ctx),           needsClase: false, needsWeek: false, label: 'Mis gomets' },
    semaforo:         { ui: () => uiSemaforo(),         bind: (el, ctx) => bindSemaforo(el, ctx),         needsClase: true,  label: 'Semáforo' },
    deportes:         { ui: () => uiDeportes(),         bind: (el, ctx) => bindDeportes(el, ctx),         needsClase: false, label: 'Deportes' },
    partes:           { ui: () => uiPartes(),           bind: (el, ctx) => bindPartes(el, ctx),           needsClase: true,  label: 'Partes' },
    extraordinarias:     { ui: () => uiExtraordinarias(),     bind: (el, ctx) => bindExtraordinarias(el, ctx),     needsClase: false, label: 'Extraordinarias' },
    biblioteca:          { ui: () => uiBiblioteca(),          bind: (el, ctx) => bindBiblioteca(el, ctx),          needsClase: false, label: 'Biblioteca' },
    tiempoAprendizaje:   { ui: () => uiTiempoAprendizaje(),   bind: (el, ctx) => bindTiempoAprendizaje(el, ctx),   needsClase: false, label: 'T. Aprendizaje' },
  };
  const MODULE_ORDER = ['semaforo', 'deportes', 'partes', 'extraordinarias', 'biblioteca', 'tiempoAprendizaje'];

  // Auto-montaje en todos los contenedores del shortcode
  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('[data-lc-panel]')?.forEach(async (node)=>{ await render(node); });
  });

  async function render(root){
    try {
      const me = await window.LC_get(EP + '/auth/me');
      const modulos = me && me.ok ? modulosDe(me) : [];
      if (me && me.ok && !modulos.length) {
        root.innerHTML = uiSinClases(me.email);
        root.querySelector('#lc-logout').addEventListener('click', async ()=>{
          await window.LC_post(EP + '/auth/logout', {});
          await render(root);
        });
      } else if (me && me.ok) {
        root.innerHTML = uiShell(me.email);
        bindShell(root, modulos);
      } else {
        root.innerHTML = uiLogin();
        bindLogin(root);
      }
    } catch (_) {
      root.innerHTML = uiLogin();
      bindLogin(root);
    }
  }

  // Qué pestañas ve cada uno (1.0): el profesor de la lista del Semáforo, solo
  // sus gomets (el servidor tampoco le deja más); quien tiene acceso completo,
  // todo, más "Mis gomets" delante si además da clase.
  // En la app del Semáforo (LC_App_Semaforo) solo "Mis gomets", sea quien sea;
  // si quien entra no tiene horario, lista vacía (ver uiSinClases).
  function modulosDe(me){
    if ((window.LC_APP || {}).modo === 'semaforo') return me.profesor ? ['gomets'] : [];
    if (me.rol === 'semaforo') return ['gomets'];
    return me.profesor ? ['gomets', ...MODULE_ORDER] : MODULE_ORDER;
  }

  // App del Semáforo abierta con un email que no es de ningún profesor del
  // horario (p. ej. alguien de Acceso > Usuarios que no da clase).
  function uiSinClases(email){
    return `
      <div class="lc-card">
        <div class="lc-toolbar lc-shell-header">
          <div class="lc-meta">Sesión: <strong>${lcEscapar(email)}</strong></div>
          <button class="lc-btn lc-btn-secondary" id="lc-logout" type="button">Cerrar sesión</button>
        </div>
        <p>Este email no está en el horario del profesorado, así que no tiene clases en las que poner gomets.
           Si es un error, pide a quien administra la Liga que lo añada en <em>Profesorado</em>.</p>
      </div>`;
  }

  function uiLogin(){
    return `
      <div class="lc-card lc-login-card">
        <h3 class="lc-title">Acceso para profesorado</h3>
        <div id="lc-paso-email">
          <div class="lc-field">
            <label for="lc-email">Correo electrónico</label>
            <input type="email" id="lc-email" placeholder="tu@correo.edu" autocomplete="email"/>
          </div>
          <button class="lc-btn lc-btn-primary lc-btn-block" id="lc-go" type="button">Enviarme un código</button>
        </div>
        <div id="lc-paso-codigo" style="display:none">
          <p id="lc-aviso" class="lc-meta"></p>
          <div class="lc-field">
            <label for="lc-codigo">Código de 6 dígitos</label>
            <input type="text" id="lc-codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*"/>
          </div>
          <button class="lc-btn lc-btn-primary lc-btn-block" id="lc-verify" type="button">Entrar</button>
          <button class="lc-btn lc-btn-secondary lc-btn-block" id="lc-otro" type="button" style="margin-top:8px">Usar otro email</button>
        </div>
        <div id="lc-error" class="lc-alert lc-alert-danger" style="display:none; margin-top:12px" aria-live="polite"></div>
        <p class="lc-meta">* Solo los emails dados de alta por el administrador reciben código.</p>
      </div>`;
  }

  // Login en dos pasos: email -> código recibido por correo -> sesión.
  // La sesión vive solo en una cookie HttpOnly; el navegador no guarda token.
  function bindLogin(root){
    const pasoEmail = root.querySelector('#lc-paso-email');
    const pasoCodigo = root.querySelector('#lc-paso-codigo');
    const btn = root.querySelector('#lc-go');
    const inp = root.querySelector('#lc-email');
    const btnVer = root.querySelector('#lc-verify');
    const inpCod = root.querySelector('#lc-codigo');
    const err = root.querySelector('#lc-error');
    function showErr(msg){ err.textContent = msg; err.style.display = 'block'; }

    btn.addEventListener('click', async ()=>{
      err.style.display = 'none';
      const email = (inp.value||'').trim();
      if(!email){ return showErr('Introduce tu email.'); }
      setButtonLoading(btn, true);
      try{
        const res = await window.LC_post(EP + '/auth/login', { email });
        setButtonLoading(btn, false);
        root.querySelector('#lc-aviso').textContent = res.codigo_demo
          ? `Demo: tu código es ${res.codigo_demo}. En un centro real llegaría por email.`
          : (res.mensaje || 'Revisa tu correo.');
        if (res.codigo_demo) inpCod.value = res.codigo_demo;
        pasoEmail.style.display = 'none';
        pasoCodigo.style.display = '';
        inpCod.focus();
      } catch(e){
        setButtonLoading(btn, false);
        showErr(e.message || 'Error de conexión. Inténtalo de nuevo.');
      }
    });

    btnVer.addEventListener('click', async ()=>{
      err.style.display = 'none';
      const codigo = (inpCod.value||'').replace(/\D/g, '');
      if(codigo.length !== 6){ return showErr('El código tiene 6 dígitos.'); }
      setButtonLoading(btnVer, true);
      try{
        await window.LC_post(EP + '/auth/verify', { email: (inp.value||'').trim(), codigo });
        await render(root);
      } catch(e){
        setButtonLoading(btnVer, false);
        showErr(e.message || 'Error de conexión. Inténtalo de nuevo.');
      }
    });

    root.querySelector('#lc-otro').addEventListener('click', ()=>{
      err.style.display = 'none';
      inpCod.value = '';
      pasoCodigo.style.display = 'none';
      pasoEmail.style.display = '';
      inp.focus();
    });

    inp.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ btn.click(); } });
    inpCod.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ btnVer.click(); } });
  }

  function uiShell(email){
    const claseOptions = lcClases().map(c => `<option value="${lcEscapar(c)}">${lcEscapar(c)}</option>`).join('');
    return `
      <div class="lc-card">
        <div class="lc-toolbar lc-shell-header">
          <div class="lc-meta">Sesión: <strong>${lcEscapar(email)}</strong></div>
          <button class="lc-btn lc-btn-secondary" id="lc-logout" type="button">Cerrar sesión</button>
        </div>

        <div class="lc-toolbar lc-context-bar">
          <div class="lc-field" id="lc-context-clase-field">
            <label for="lc-clase">Clase/Grupo</label>
            <select id="lc-clase">${claseOptions}</select>
          </div>
          <div class="lc-field" id="lc-context-week-field">
            <label for="lc-week">Semana</label>
            <select id="lc-week"></select>
          </div>
        </div>

        <div class="lc-tabs" role="tablist"></div>

        <div class="lc-tab-panel" id="lc-panel-content" role="tabpanel"></div>
      </div>`;
  }

  function bindShell(root, orden){
    const logoutBtn   = root.querySelector('#lc-logout');
    const claseField  = root.querySelector('#lc-context-clase-field');
    const weekField   = root.querySelector('#lc-context-week-field');
    const contextBar  = root.querySelector('.lc-context-bar');
    const claseSelect = root.querySelector('#lc-clase');
    const weekSelect  = root.querySelector('#lc-week');
    const tabsEl      = root.querySelector('.lc-tabs');
    const panel       = root.querySelector('#lc-panel-content');

    initWeekSelect(weekSelect);

    const state = { tab: orden[0], dirty: false };
    let moduleHandle = null;
    let lastClase = claseSelect.value;
    let lastWeek  = weekSelect.value;

    function buildCtx(){
      const wk = parseWeekInput(weekSelect.value) || {};
      return {
        clase: claseSelect.value,
        year: wk.year,
        week: wk.week,
        onDirtyChange(isDirty){ state.dirty = !!isDirty; },
      };
    }

    function confirmDiscardIfDirty(){
      if (!state.dirty) return true;
      return window.confirm('Tienes cambios sin guardar en este formulario. ¿Quieres continuar y descartarlos?');
    }

    function renderTabs(){
      // Con una sola pestaña (profesor del Semáforo) no se pinta la barra.
      tabsEl.style.display = orden.length > 1 ? '' : 'none';
      tabsEl.innerHTML = orden.map(key => `
        <button class="lc-tab${key === state.tab ? ' active' : ''}" data-tab="${key}" role="tab" aria-selected="${key === state.tab}">${MODULES[key].label}</button>
      `).join('');
    }

    function switchTab(tab){
      state.tab = tab;
      state.dirty = false;
      renderTabs();
      const needsWeek = MODULES[tab].needsWeek !== false;
      claseField.style.display = MODULES[tab].needsClase ? '' : 'none';
      weekField.style.display = needsWeek ? '' : 'none';
      contextBar.style.display = (MODULES[tab].needsClase || needsWeek) ? '' : 'none';
      panel.innerHTML = MODULES[tab].ui();
      moduleHandle = MODULES[tab].bind(panel, buildCtx()) || null;
    }

    tabsEl.addEventListener('click', (e)=>{
      const btn = e.target.closest('.lc-tab');
      if (!btn) return;
      const tab = btn.dataset.tab;
      if (tab === state.tab) return;
      if (!confirmDiscardIfDirty()) return;
      switchTab(tab);
    });

    claseSelect.addEventListener('change', ()=>{
      if (!confirmDiscardIfDirty()){ claseSelect.value = lastClase; return; }
      lastClase = claseSelect.value;
      state.dirty = false;
      moduleHandle?.refresh(buildCtx());
    });

    weekSelect.addEventListener('change', ()=>{
      if (!confirmDiscardIfDirty()){ weekSelect.value = lastWeek; return; }
      lastWeek = weekSelect.value;
      state.dirty = false;
      moduleHandle?.refresh(buildCtx());
    });

    logoutBtn.addEventListener('click', async ()=>{
      if (!confirmDiscardIfDirty()) return;
      await window.LC_post(EP + '/auth/logout', {});
      await render(root);
    });

    switchTab(state.tab);
  }

  // Opciones del selector de semana. Cada semana muestra su trimestre y, además,
  // van agrupadas por <optgroup>: el nombre del trimestre se ve tanto con el
  // desplegable abierto (la cabecera del grupo) como cerrado (el sufijo de la
  // opción elegida). Sin trimestres configurados se pintan opciones planas, para
  // no meter un grupo "Sin trimestre" que sobra.
  function weekOptionsHtml(weeks){
    if (!weeks.some(w => w.trimestre)){
      return weeks.map(w => `<option value="${w.value}">${w.label}</option>`).join('');
    }
    const html = [];
    let grupoActual = null;
    weeks.forEach(w => {
      const grupo = w.trimestre || 'Fuera de trimestre';
      if (grupo !== grupoActual){
        if (grupoActual !== null) html.push('</optgroup>');
        html.push(`<optgroup label="${grupo}">`);
        grupoActual = grupo;
      }
      const sufijo = w.trimestre ? ` · ${w.trimestre}` : '';
      html.push(`<option value="${w.value}">${w.label}${sufijo}</option>`);
    });
    html.push('</optgroup>');
    return html.join('');
  }

  // Rellena el selector con las semanas de la temporada configurada (o del curso
  // octubre->junio si no hay ninguna) y preselecciona la semana actual.
  function initWeekSelect(selectEl){
    const weeks = seasonWeeksFor(new Date());
    selectEl.innerHTML = weekOptionsHtml(weeks);
    if (!weeks.length) return;

    const { year, week } = currentISOWeek();
    const nowISO = `${year}-W${String(week).padStart(2,'0')}`;
    if (weeks.some(w => w.value === nowISO)){ selectEl.value = nowISO; return; }

    // Hoy cae fuera de la temporada: se elige el extremo más cercano en vez de
    // dejar la primera opción. Dejarla es lo que hacía que, con el curso aún sin
    // empezar, todo se guardara en la primera semana del curso ANTERIOR sin que
    // nada lo advirtiera, y esos registros luego no salían al filtrar.
    const orden = (v) => { const p = parseWeekInput(v); return p ? p.year * 100 + p.week : 0; };
    const ahora = year * 100 + week;
    selectEl.value = ahora < orden(weeks[0].value) ? weeks[0].value : weeks[weeks.length - 1].value;
  }
})();
