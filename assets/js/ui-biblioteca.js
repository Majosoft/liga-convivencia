// UI Biblioteca
// Mismo contrato que ui-semaforo.js. Este módulo no usa la "Clase" de la barra
// de contexto (los participantes ya llevan su propia clase), solo year/week.
// La lista de actividades y su modo (participantes/enfrentamiento) vienen de
// window.LC_SETTINGS.actividades, configurable desde Ajustes > Liga Convivencia.

function lcActividadesList(){
  return window.LC_SETTINGS?.actividades || [];
}

function lcActividadConfig(value){
  return lcActividadesList().find(a => a.value === value) || null;
}

function uiBiblioteca(){
  const claseOptions = lcClases().map(c => `<option>${c}</option>`).join('');
  const actividadOptions = lcActividadesList().map(a => `<option value="${a.value}">${a.label}</option>`).join('');
  return `
    <div>
      <h4 class="lc-module-title">Biblioteca – registro de actividades</h4>

      <div class="lc-module-split">
      <div class="lc-module-form">

      <div class="lc-field">
        <label for="bib-actividad">Actividad</label>
        <select id="bib-actividad">${actividadOptions}</select>
      </div>

      <!-- Modo enfrentamiento: clase A vs clase B + resultado -->
      <div id="bib-enfrentamiento-fields" class="lc-form-grid" style="display:none">
        <div class="lc-field">
          <label for="bib-clase-a">Partida</label>
          <div class="lc-matchup">
            <select id="bib-clase-a">${claseOptions}</select>
            <span class="lc-matchup-vs">VS</span>
            <select id="bib-clase-b" aria-label="Clase B">${claseOptions}</select>
          </div>
        </div>
        <div class="lc-field">
          <label for="bib-ganador">Resultado</label>
          <select id="bib-ganador">
            <option value="empate">Empate</option>
            <option value="clase-a">Gana Clase A</option>
            <option value="clase-b">Gana Clase B</option>
          </select>
        </div>
      </div>

      <div id="bib-alumnos-fields" class="lc-form-grid" style="display:none">
        <div class="lc-field">
          <label for="bib-alumno-a">Alumno de <span id="bib-alumno-a-clase"></span></label>
          ${alumnoPickerHtml('bib-alumno-a')}
        </div>
        <div class="lc-field">
          <label for="bib-alumno-b">Alumno de <span id="bib-alumno-b-clase"></span></label>
          ${alumnoPickerHtml('bib-alumno-b')}
        </div>
      </div>

      <!-- Modo participantes: lista de participantes + valoración -->
      <div id="bib-participantes-fields">
        <div class="lc-field">
          <label>Participantes</label>
          <div id="bib-participantes-list"></div>
          <button type="button" id="bib-add-participante" class="lc-btn lc-btn-secondary lc-btn-add">+ Añadir</button>
        </div>

        <div class="lc-field">
          <label for="bib-valoracion">Valoración</label>
          <select id="bib-valoracion"></select>
        </div>
      </div>

      <div class="lc-field">
        <label for="bib-notas">Notas</label>
        <textarea id="bib-notas" placeholder="Observaciones"></textarea>
      </div>

      <div id="bib-edit-banner" class="lc-edit-mode-bar" style="display:none">
        <span id="bib-edit-banner-msg">✏️ Editando este registro</span>
        <button type="button" id="bib-cancel-edit" class="lc-btn lc-btn-secondary lc-btn-sm">Cancelar</button>
      </div>

      <div class="lc-actions">
        <button class="lc-btn lc-btn-primary" id="bib-add" type="button">Guardar actividad</button>
        <span id="bib-msg" class="lc-status" aria-live="polite"></span>
      </div>

      </div>

      <div class="lc-module-side">
      <div class="lc-panel-section">
        <h5 class="lc-section-title">Actividades registradas</h5>
        <div id="bib-list" class="lc-meta">Sin actividades esta semana.</div>
      </div>

      <div class="lc-panel-section">
        <h5 class="lc-section-title">Clasificación – <span id="bib-ranking-label"></span></h5>
        <div id="bib-ranking" class="lc-meta">Cargando…</div>
      </div>
      </div>

      </div>
    </div>
  `;
}

function bindBiblioteca(container, ctx){
  function markDirty(){ ctx.onDirtyChange?.(true); }

  const actividad = container.querySelector('#bib-actividad');
  const enfrentamientoFields = container.querySelector('#bib-enfrentamiento-fields');
  const participantesFields = container.querySelector('#bib-participantes-fields');
  const alumnosFields = container.querySelector('#bib-alumnos-fields');
  const claseALabel = container.querySelector('#bib-alumno-a-clase');
  const claseBLabel = container.querySelector('#bib-alumno-b-clase');
  const pickerA = bindAlumnoPicker(container.querySelector('#bib-alumno-a').closest('[data-alumno-picker]'), { onChange: markDirty });
  const pickerB = bindAlumnoPicker(container.querySelector('#bib-alumno-b').closest('[data-alumno-picker]'), { onChange: markDirty });
  const claseA = container.querySelector('#bib-clase-a');
  const claseB = container.querySelector('#bib-clase-b');
  const ganador = container.querySelector('#bib-ganador');
  const valoracion = container.querySelector('#bib-valoracion');
  const notas = container.querySelector('#bib-notas');
  const add   = container.querySelector('#bib-add');
  const msg   = container.querySelector('#bib-msg');
  const list  = container.querySelector('#bib-list');
  const addParticipanteBtn = container.querySelector('#bib-add-participante');
  const participantesListEl = container.querySelector('#bib-participantes-list');
  const rankingLabel = container.querySelector('#bib-ranking-label');
  const rankingEl = container.querySelector('#bib-ranking');
  const editBanner = container.querySelector('#bib-edit-banner');
  const editBannerMsg = container.querySelector('#bib-edit-banner-msg');
  const cancelEditBtn = container.querySelector('#bib-cancel-edit');

  let currentRows = [];
  let editingId = null;

  function markDirty(){ ctx.onDirtyChange?.(true); }

  const participantList = createParticipantList({
    container: participantesListEl,
    classes: lcClases(),
    onChange: markDirty,
  });

  [claseA, claseB, ganador, valoracion].forEach(el => el.addEventListener('change', markDirty));
  [claseA, claseB].forEach(el => el.addEventListener('change', () => { if (modo() === 'alumnos') syncAlumnos(); }));
  notas.addEventListener('input', markDirty);
  addParticipanteBtn.addEventListener('click', () => participantList.add());

  function modo(){
    return lcActividadConfig(actividad.value)?.modo || 'participantes';
  }

  function exitEditMode(){
    editingId = null;
    editBanner.style.display = 'none';
  }

  function enterEditMode(row){
    editingId = parseInt(row.id, 10);
    const m = modo();
    const isEnfrentamiento = (m === 'enfrentamiento' || m === 'alumnos');

    if (isEnfrentamiento) {
      editBannerMsg.textContent = '✏️ Editando esta partida';
      // Asignar .value programáticamente NO dispara el evento change en <select>
      claseA.value = row.clase_a;
      claseB.value = row.clase_b;
      ganador.value = row.ganador || 'empate';
      if (m === 'alumnos') {
        claseALabel.textContent = row.clase_a;
        claseBLabel.textContent = row.clase_b;
        pickerA.setClase(row.clase_a, row.alumno_a);
        pickerB.setClase(row.clase_b, row.alumno_b);
      }
    } else {
      editBannerMsg.textContent = '✏️ Editando este registro';
      let parts = [];
      try { parts = JSON.parse(row.participantes) || []; } catch(e) {}
      if (Array.isArray(parts)) participantList.reset(parts.map(p => ({ clase: p.clase, nombre: p.nombre })));
      valoracion.value = String(row.puntos);
    }

    notas.value = row.notas || '';
    editBanner.style.display = 'flex';
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function renderValoracionOptions(){
    const niveles = lcActividadConfig(actividad.value)?.niveles || [];
    valoracion.innerHTML = niveles.map(n => `<option value="${n.puntos}">${n.label} (${n.puntos} puntos)</option>`).join('');
  }

  async function syncAlumnos(){
    claseALabel.textContent = claseA.value || '';
    claseBLabel.textContent = claseB.value || '';
    await pickerA.setClase(claseA.value);
    await pickerB.setClase(claseB.value);
  }

  function toggleFieldsByModo(){
    const m = modo();
    // "alumnos" usa el mismo formulario que enfrentamiento (clase A vs clase B,
    // resultado) y añade encima el alumno que compite por cada clase.
    const isEnfrentamiento = (m === 'enfrentamiento' || m === 'alumnos');
    const isAlumnos = m === 'alumnos';
    enfrentamientoFields.style.display = isEnfrentamiento ? '' : 'none';
    participantesFields.style.display = isEnfrentamiento ? 'none' : '';
    alumnosFields.style.display = isAlumnos ? '' : 'none';
    if (isAlumnos) syncAlumnos();
    if (!isEnfrentamiento) renderValoracionOptions();
  }

  function renderList(){
    if(!currentRows.length){
      list.innerHTML = '<div class="lc-meta">Sin actividades esta semana.</div>';
      return;
    }
    const m = modo();
    const isAlumnos = m === 'alumnos';
    const isEnfrentamiento = (m === 'enfrentamiento' || isAlumnos);

    if (isEnfrentamiento) {
      const ganadorLabel = isAlumnos
        ? { 'empate':'Empate', 'clase-a':'Gana Alumno A', 'clase-b':'Gana Alumno B' }
        : { 'empate':'Empate', 'clase-a':'Gana Clase A', 'clase-b':'Gana Clase B' };
      // En modo por alumnos se muestra quién jugó, que es el dato que distingue
      // una partida de otra entre las mismas dos clases.
      const quien = (r, lado) => isAlumnos
        ? `${r['alumno_' + lado + '_nombre'] || '—'} <span class="lc-meta">(${r['clase_' + lado]})</span>`
        : r['clase_' + lado];
      const rowsHtml = currentRows.map(r => `
        <tr>
          <td>${quien(r, 'a')}</td>
          <td style="text-align:center">vs</td>
          <td>${quien(r, 'b')}</td>
          <td>${ganadorLabel[r.ganador] || r.ganador}</td>
          <td>${r.notas ? r.notas : ''}</td>
          <td><button type="button" class="lc-btn lc-btn-secondary lc-edit-row-btn lc-btn-xs" data-row-id="${r.id}">Editar</button></td>
        </tr>`).join('');
      list.innerHTML = `
        <div class="lc-table-container">
          <table class="lc-table">
            <thead><tr><th>${isAlumnos ? 'Alumno A' : 'Clase A'}</th><th></th><th>${isAlumnos ? 'Alumno B' : 'Clase B'}</th><th>Resultado</th><th>Notas</th><th></th></tr></thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>`;
    } else {
      const rowsHtml = currentRows.map(r => {
        let participantesText = r.participantes || '';
        try {
          const parsed = JSON.parse(r.participantes);
          if (Array.isArray(parsed)) participantesText = parsed.map(p => `${p.clase}: ${p.nombre}`).join(', ');
        } catch (e) { /* texto plano */ }

        return `
          <tr>
            <td>${r.actividad}</td>
            <td>${participantesText}</td>
            <td style="text-align:center">${r.puntos}</td>
            <td>${r.notas ? r.notas : ''}</td>
            <td><button type="button" class="lc-btn lc-btn-secondary lc-edit-row-btn lc-btn-xs" data-row-id="${r.id}">Editar</button></td>
          </tr>`;
      }).join('');

      list.innerHTML = `
        <div class="lc-table-container">
          <table class="lc-table">
            <thead>
              <tr>
                <th>Actividad</th>
                <th>Participantes</th>
                <th>Puntos</th>
                <th>Notas</th>
                <th></th>
              </tr>
            </thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>`;
    }

    list.querySelectorAll('.lc-edit-row-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const rowId = parseInt(btn.dataset.rowId, 10);
        const row = currentRows.find(r => parseInt(r.id, 10) === rowId);
        if (row) enterEditMode(row);
      });
    });
  }

  function prefillEnfrentamiento(silent = false){
    // Solo entre clases: en modo por alumnos hay varias partidas por semana
    // entre las mismas clases y precargar "la" partida sería incorrecto.
    if (modo() !== 'enfrentamiento') return;
    const A = claseA.value, B = claseB.value;
    let row = currentRows.find(r => r.clase_a===A && r.clase_b===B);
    let invertida = false;
    if(!row){ row = currentRows.find(r => r.clase_a===B && r.clase_b===A); invertida = !!row; }
    if(row){
      // El ganador guardado es relativo al orden de la fila: si las clases están
      // elegidas al revés, "gana A" de la fila es "gana B" en el formulario.
      const g = row.ganador || 'empate';
      ganador.value = (invertida && g !== 'empate') ? (g === 'clase-a' ? 'clase-b' : 'clase-a') : g;
      notas.value = row.notas || '';
      if (!silent) setStatus(msg, 'Partida existente cargada', 'info');
    } else if (!silent) {
      setStatus(msg, '', 'info');
    }
  }

  cancelEditBtn.addEventListener('click', () => {
    exitEditMode();
    if (modo() === 'enfrentamiento') {
      ganador.value = 'empate';
    } else {
      participantList.reset([]);
    }
    notas.value = '';
    setStatus(msg, '', 'info');
  });

  async function loadList(){
    setStatus(msg, '', 'info');
    if(!ctx.year || !ctx.week){ return; }
    const act = actividad.value;
    try{
      const q = new URLSearchParams({actividad: act, year: ctx.year, week: ctx.week});
      const res = await window.LC_get(window.LC_EP + '/biblioteca?' + q.toString());
      currentRows = res?.actividades || [];
      renderList();
      // En modo por alumnos cada partida es un registro propio, así que no tiene
      // sentido precargar "la partida de esas dos clases".
      if (modo() === 'enfrentamiento') prefillEnfrentamiento();
      ctx.onDirtyChange?.(false);
    }catch(e){
      currentRows = [];
      renderList();
      setStatus(msg, 'Error al cargar actividades', 'error');
      console.error('GET /biblioteca', e);
    }
    loadRanking();
  }

  // Clasificación acumulada (toda la temporada) de la actividad seleccionada.
  async function loadRanking(){
    const act = actividad.value;
    rankingLabel.textContent = lcActividadConfig(act)?.label || act;
    rankingEl.innerHTML = 'Cargando…';
    try{
      const res = await window.LC_get(window.LC_EP + '/clasificacion');
      const entry = res?.por_actividad?.find(a => a.value === act);
      rankingEl.innerHTML = renderRankingConToggle(entry?.ranking, entry?.ranking_alumnos);
      bindRankingToggles(rankingEl);
    }catch(e){
      rankingEl.innerHTML = '<div class="lc-meta">Error al cargar la clasificación.</div>';
      console.error('GET /clasificacion', e);
    }
  }

  // Al cambiar actividad o clases, salir de modo edición
  actividad.addEventListener('change', ()=>{ exitEditMode(); toggleFieldsByModo(); loadList(); });
  claseA.addEventListener('change', ()=>{ exitEditMode(); prefillEnfrentamiento(); });
  claseB.addEventListener('change', ()=>{ exitEditMode(); prefillEnfrentamiento(); });

  toggleFieldsByModo();
  loadList();

  add.addEventListener('click', async ()=>{
    if(!ctx.year || !ctx.week) return setStatus(msg, 'Semana inválida', 'error');

    const act = actividad.value;
    const m = modo();
    const isAlumnos = m === 'alumnos';
    const isEnfrentamiento = (m === 'enfrentamiento' || isAlumnos);
    const notas_val = notas.value.trim();

    if (isEnfrentamiento && claseA.value === claseB.value) {
      return setStatus(msg, 'Las clases deben ser distintas', 'error');
    }
    if (isAlumnos && (!pickerA.value() || !pickerB.value())) {
      return setStatus(msg, 'Selecciona el alumno de cada clase', 'error');
    }
    if (!isEnfrentamiento && participantList.getValues().length === 0) {
      return setStatus(msg, 'Añade al menos un participante', 'error');
    }

    setButtonLoading(add, true);

    const payload = {
      actividad: act,
      year: ctx.year,
      week: ctx.week,
      notas: notas_val,
    };

    if (editingId) payload.id = editingId;

    if (isEnfrentamiento) {
      payload.clase_a = claseA.value;
      payload.clase_b = claseB.value;
      payload.ganador = ganador.value;
      if (isAlumnos) {
        payload.alumno_a = pickerA.value();
        payload.alumno_b = pickerB.value();
      }
    } else {
      payload.participantes = JSON.stringify(participantList.getValues());
      payload.puntos = parseInt(valoracion.value, 10);
    }

    try{
      const res = await window.LC_post(window.LC_EP + '/biblioteca', payload);
      setButtonLoading(add, false);
      if(res && res.ok){
        exitEditMode();
        currentRows = res?.actividades || [];
        renderList();
        setStatus(msg, 'Actividad guardada ✅', 'success');
        ctx.onDirtyChange?.(false);
        if (isEnfrentamiento) prefillEnfrentamiento(true);
        else { participantList.reset([]); notas.value = ''; }
        loadRanking();
      } else {
        setStatus(msg, 'Error al guardar: ' + (res?.error || 'Error desconocido'), 'error');
      }
    }catch(e){
      setButtonLoading(add, false);
      setStatus(msg, 'Error al guardar: ' + (e.message||''), 'error');
      console.error('POST /biblioteca', payload, e);
    }
  });

  return {
    refresh(newCtx){ ctx = newCtx; exitEditMode(); loadList(); }
  };
}
