// UI Actividades Extraordinarias
// Módulo independiente: el profesor crea actividades con nombre libre,
// enrolla clases con puntuación fija (10/8/5/3/0) y guarda.
// No usa el selector global de "Clase" (needsClase: false), solo year/week del ctx.

const EXT_PUNTOS = [10, 8, 5, 3, 0];

function uiExtraordinarias(){
  return `
    <div>
      <h4 class="lc-module-title">Actividades Extraordinarias</h4>

      <div class="lc-module-split">
      <div class="lc-module-form">

      <div class="lc-field">
        <label for="ext-nombre">Nombre de la actividad</label>
        <input type="text" id="ext-nombre" placeholder="Ej: Concurso de poesía, Olimpiada matemática…" autocomplete="off"/>
      </div>

      <div class="lc-field">
        <label>Clases participantes</label>
        <div id="ext-clases-list"></div>
        <button type="button" id="ext-add-clase" class="lc-btn lc-btn-secondary lc-btn-add">+ Agregar clase</button>
      </div>

      <div class="lc-field">
        <label for="ext-notas">Observaciones</label>
        <textarea id="ext-notas" placeholder="Detalles adicionales sobre la actividad…"></textarea>
      </div>

      <div id="ext-edit-banner" class="lc-edit-mode-bar" style="display:none">
        <span>✏️ Editando esta actividad</span>
        <button type="button" id="ext-cancel-edit" class="lc-btn lc-btn-secondary lc-btn-sm">Cancelar</button>
      </div>

      <div class="lc-actions">
        <button class="lc-btn lc-btn-primary" id="ext-add" type="button">Guardar actividad</button>
        <span id="ext-msg" class="lc-status" aria-live="polite"></span>
      </div>

      </div>

      <div class="lc-module-side">
      <div class="lc-panel-section">
        <h5 class="lc-section-title">Actividades de la semana seleccionada</h5>
        <div id="ext-list" class="lc-meta">Sin actividades esta semana.</div>
      </div>

      <div class="lc-panel-section">
        <h5 class="lc-section-title">Clasificación – Extraordinarias</h5>
        <div id="ext-ranking" class="lc-meta">Cargando…</div>
      </div>
      </div>

      </div>
    </div>
  `;
}

function bindExtraordinarias(container, ctx){
  const nombreInput  = container.querySelector('#ext-nombre');
  const clasesList   = container.querySelector('#ext-clases-list');
  const addClaseBtn  = container.querySelector('#ext-add-clase');
  const notasInput   = container.querySelector('#ext-notas');
  const addBtn       = container.querySelector('#ext-add');
  const msg          = container.querySelector('#ext-msg');
  const list         = container.querySelector('#ext-list');
  const rankingEl    = container.querySelector('#ext-ranking');
  const editBanner   = container.querySelector('#ext-edit-banner');
  const cancelEditBtn = container.querySelector('#ext-cancel-edit');

  let currentActividades = []; // lista devuelta por el GET
  let editingNombre = null;    // nombre original de la actividad en edición

  function markDirty(){ ctx.onDirtyChange?.(true); }

  // ── Opciones de puntos ──────────────────────────────────────────────────────

  function puntosOptions(selected){
    return EXT_PUNTOS.map(p =>
      `<option value="${p}"${p === selected ? ' selected' : ''}>${p} puntos</option>`
    ).join('');
  }

  // ── Fila dinámica de clase ──────────────────────────────────────────────────

  function addClaseRow(claseVal, puntosVal){
    const claseOptions = lcClases().map(c =>
      `<option${c === claseVal ? ' selected' : ''}>${lcEscapar(c)}</option>`
    ).join('');

    const row = document.createElement('div');
    row.className = 'lc-participant-row';
    row.innerHTML = `
      <select class="ext-clase-sel">${claseOptions}</select>
      <select class="ext-puntos-sel">${puntosOptions(puntosVal ?? EXT_PUNTOS[0])}</select>
      <button type="button" class="lc-btn lc-btn-icon lc-btn-secondary ext-remove-btn" aria-label="Quitar clase">×</button>
    `;
    row.querySelector('.ext-remove-btn').addEventListener('click', () => { row.remove(); markDirty(); });
    row.querySelector('.ext-clase-sel').addEventListener('change', markDirty);
    row.querySelector('.ext-puntos-sel').addEventListener('change', markDirty);
    clasesList.appendChild(row);
    markDirty();
  }

  function getClasesValues(){
    return [...clasesList.querySelectorAll('.lc-participant-row')].map(row => ({
      clase:  row.querySelector('.ext-clase-sel').value,
      puntos: parseInt(row.querySelector('.ext-puntos-sel').value, 10),
    }));
  }

  function resetClases(){
    clasesList.innerHTML = '';
  }

  addClaseBtn.addEventListener('click', () => addClaseRow());

  // ── Edit mode ───────────────────────────────────────────────────────────────

  function exitEditMode(){
    editingNombre = null;
    editBanner.style.display = 'none';
  }

  function enterEditMode(actividad){
    editingNombre = actividad.nombre;
    nombreInput.value = actividad.nombre;
    notasInput.value = actividad.notas || '';
    resetClases();
    (actividad.clases || []).forEach(c => addClaseRow(c.clase, c.puntos));
    editBanner.style.display = 'flex';
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  cancelEditBtn.addEventListener('click', () => {
    exitEditMode();
    nombreInput.value = '';
    notasInput.value = '';
    resetClases();
    setStatus(msg, '', 'info');
  });

  nombreInput.addEventListener('input', markDirty);
  notasInput.addEventListener('input', markDirty);

  // ── Render lista de actividades guardadas ───────────────────────────────────

  function renderList(){
    if (!currentActividades.length) {
      list.innerHTML = '<div class="lc-meta">Sin actividades esta semana.</div>';
      return;
    }

    const rowsHtml = currentActividades.map(act => {
      const clasesText = (act.clases || [])
        .map(c => `${lcEscapar(c.clase)} (${c.puntos} pts)`)
        .join(', ');
      return `
        <tr>
          <td><strong>${lcEscapar(act.nombre)}</strong></td>
          <td>${clasesText}</td>
          <td>${lcEscapar(act.notas)}</td>
          <td><button type="button" class="lc-btn lc-btn-secondary lc-edit-row-btn lc-btn-xs" data-nombre="${encodeURIComponent(act.nombre)}">Editar</button></td>
        </tr>`;
    }).join('');

    list.innerHTML = `
      <div class="lc-table-container">
        <table class="lc-table">
          <thead>
            <tr>
              <th>Actividad</th>
              <th>Clases participantes</th>
              <th>Observaciones</th>
              <th></th>
            </tr>
          </thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>`;

    list.querySelectorAll('.lc-edit-row-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const nombre = decodeURIComponent(btn.dataset.nombre);
        const act = currentActividades.find(a => a.nombre === nombre);
        if (act) enterEditMode(act);
      });
    });
  }

  // ── Carga de datos ──────────────────────────────────────────────────────────

  async function loadList(){
    setStatus(msg, '', 'info');
    if (!ctx.year || !ctx.week) return;
    try {
      const q = new URLSearchParams({ year: ctx.year, week: ctx.week });
      const res = await window.LC_get(window.LC_EP + '/extraordinarias?' + q.toString());
      currentActividades = res?.actividades || [];
      renderList();
      ctx.onDirtyChange?.(false);
    } catch(e) {
      currentActividades = [];
      renderList();
      setStatus(msg, 'Error al cargar actividades', 'error');
      console.error('GET /extraordinarias', e);
    }
    loadRanking();
  }

  async function loadRanking(){
    rankingEl.innerHTML = 'Cargando…';
    try {
      const res = await window.LC_get(window.LC_EP + '/clasificacion');
      const ranking = (res?.clasificacion || [])
        .map(c => ({ clase: c.clase, puntos: c.extraordinarias || 0, escudo_url: c.escudo_url }))
        .sort((a, b) => b.puntos - a.puntos);
      rankingEl.innerHTML = renderRankingTable(ranking);
    } catch(e) {
      rankingEl.innerHTML = '<div class="lc-meta">Error al cargar la clasificación.</div>';
      console.error('GET /clasificacion (extraordinarias ranking)', e);
    }
  }

  // ── Guardar ─────────────────────────────────────────────────────────────────

  addBtn.addEventListener('click', async () => {
    if (!ctx.year || !ctx.week) return setStatus(msg, 'Semana inválida', 'error');

    const nombre = nombreInput.value.trim();
    if (!nombre) return setStatus(msg, 'Introduce el nombre de la actividad', 'error');

    const clases = getClasesValues();
    if (!clases.length) return setStatus(msg, 'Añade al menos una clase participante', 'error');
    const repetida = clases.find((c, i) => clases.findIndex(o => o.clase === c.clase) !== i);
    if (repetida) return setStatus(msg, `La clase ${repetida.clase} está repetida`, 'error');

    // Guardar con el nombre de otra actividad de esta semana la sustituye entera
    // (el servidor reemplaza por nombre + semana): se avisa antes.
    const pisada = nombre !== editingNombre && currentActividades.some(a => a.nombre === nombre);
    if (pisada && !window.confirm(`Ya hay una actividad "${nombre}" esta semana. ¿Sustituirla por esta?`)) return;

    const payload = {
      nombre,
      year: ctx.year,
      week: ctx.week,
      clases: JSON.stringify(clases),
      notas: notasInput.value.trim(),
    };

    if (editingNombre && editingNombre !== nombre) {
      payload.original_nombre = editingNombre;
    }

    setButtonLoading(addBtn, true);
    try {
      const res = await window.LC_post(window.LC_EP + '/extraordinarias', payload);
      setButtonLoading(addBtn, false);
      if (res && res.ok) {
        exitEditMode();
        currentActividades = res.actividades || [];
        renderList();
        nombreInput.value = '';
        notasInput.value = '';
        resetClases();
        setStatus(msg, 'Actividad guardada ✅', 'success');
        ctx.onDirtyChange?.(false);
        loadRanking();
      } else {
        setStatus(msg, 'Error: ' + (res?.error || 'Error desconocido'), 'error');
      }
    } catch(e) {
      setButtonLoading(addBtn, false);
      setStatus(msg, 'Error al guardar: ' + (e.message || ''), 'error');
      console.error('POST /extraordinarias', payload, e);
    }
  });

  // Carga inicial
  loadList();

  return {
    refresh(newCtx){ ctx = newCtx; exitEditMode(); loadList(); }
  };
}
