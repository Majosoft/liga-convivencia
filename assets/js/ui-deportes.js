// UI Deportes
// Mismo contrato que ui-semaforo.js. Este módulo no usa la "Clase" de la barra
// de contexto (cada partido define sus propios equipos A/B), solo year/week.
// La lista de deportes y su modo de puntuación (enfrentamiento/individual) vienen
// de window.LC_SETTINGS.deportes, configurable desde Ajustes > Liga Convivencia.

function lcDeportesList(){
  return window.LC_SETTINGS?.deportes || [];
}

function lcDeporteConfig(value){
  return lcDeportesList().find(d => d.value === value) || null;
}

function uiDeportes(){
  const teamOptions = lcClases().map(c => `<option>${lcEscapar(c)}</option>`).join('');
  const sportOptions = lcDeportesList().map(d => `<option value="${lcEscapar(d.value)}">${lcEscapar(d.label)}</option>`).join('');
  return `
    <div>
      <h4 class="lc-module-title">Deportes – registro de partidos</h4>

      <div class="lc-module-split">
      <div class="lc-module-form">

      <div class="lc-field">
        <label for="dep-sport">Deporte</label>
        <select id="dep-sport">${sportOptions}</select>
      </div>

      <div id="dep-enfrentamiento-fields" class="lc-form-grid">
        <div class="lc-field">
          <label for="dep-team-a">Partido</label>
          <div class="lc-matchup">
            <select id="dep-team-a">${teamOptions}</select>
            <span class="lc-matchup-vs">VS</span>
            <select id="dep-team-b" aria-label="Equipo visitante">${teamOptions}</select>
          </div>
        </div>

        <div class="lc-field">
          <label>Marcador</label>
          <div class="lc-scoreboard">
            <div class="lc-stepper">
              <button type="button" class="lc-stepper-btn" data-step="-1" aria-label="Restar gol equipo A">−</button>
              <input type="number" id="dep-score-a" value="0" min="0" inputmode="numeric"/>
              <button type="button" class="lc-stepper-btn" data-step="1" aria-label="Sumar gol equipo A">+</button>
            </div>
            <span class="lc-scoreboard-sep">–</span>
            <div class="lc-stepper">
              <button type="button" class="lc-stepper-btn" data-step="-1" aria-label="Restar gol equipo B">−</button>
              <input type="number" id="dep-score-b" value="0" min="0" inputmode="numeric" aria-label="Marcador equipo visitante"/>
              <button type="button" class="lc-stepper-btn" data-step="1" aria-label="Sumar gol equipo B">+</button>
            </div>
          </div>
        </div>
      </div>

      <div id="dep-alumnos-fields" class="lc-form-grid" style="display:none">
        <div class="lc-field">
          <label for="dep-alumno-a">Alumno de <span id="dep-alumno-a-clase"></span></label>
          ${alumnoPickerHtml('dep-alumno-a')}
        </div>
        <div class="lc-field">
          <label for="dep-alumno-b">Alumno de <span id="dep-alumno-b-clase"></span></label>
          ${alumnoPickerHtml('dep-alumno-b')}
        </div>
      </div>

      <div class="lc-field" id="dep-participantes-block">
        <label id="dep-participantes-label">Participantes</label>
        <div id="dep-participantes-list"></div>
        <button type="button" id="dep-add-participante" class="lc-btn lc-btn-secondary lc-btn-add">+ Añadir</button>
      </div>

      <div class="lc-field">
        <label for="dep-notas">Notas</label>
        <textarea id="dep-notas" placeholder="Observaciones"></textarea>
      </div>

      <div id="dep-edit-banner" class="lc-edit-mode-bar" style="display:none">
        <span>✏️ Editando este partido</span>
        <button type="button" id="dep-cancel-edit" class="lc-btn lc-btn-secondary lc-btn-sm">Cancelar</button>
      </div>

      <div class="lc-actions">
        <button class="lc-btn lc-btn-primary" id="dep-add" type="button">Guardar</button>
        <span id="dep-msg" class="lc-status" aria-live="polite"></span>
      </div>

      </div>

      <div class="lc-module-side">
      <div class="lc-panel-section">
        <h5 class="lc-section-title">Partidos/eventos guardados</h5>
        <div id="dep-list" class="lc-meta">Sin datos esta semana.</div>
      </div>

      <div class="lc-panel-section">
        <h5 class="lc-section-title">Clasificación – <span id="dep-ranking-label"></span></h5>
        <div id="dep-ranking" class="lc-meta">Cargando…</div>
      </div>
      </div>

      </div>
    </div>
  `;
}

function bindDeportes(container, ctx){
  function markDirty(){ ctx.onDirtyChange?.(true); }

  const sport = container.querySelector('#dep-sport');
  const enfrentamientoFields = container.querySelector('#dep-enfrentamiento-fields');
  const ta    = container.querySelector('#dep-team-a');
  const tb    = container.querySelector('#dep-team-b');
  const sa    = container.querySelector('#dep-score-a');
  const sb    = container.querySelector('#dep-score-b');
  const notas = container.querySelector('#dep-notas');
  const add   = container.querySelector('#dep-add');
  const msg   = container.querySelector('#dep-msg');
  const list  = container.querySelector('#dep-list');
  const participantesLabel = container.querySelector('#dep-participantes-label');
  const addParticipanteBtn = container.querySelector('#dep-add-participante');
  const participantesListEl = container.querySelector('#dep-participantes-list');
  const rankingLabel = container.querySelector('#dep-ranking-label');
  const rankingEl = container.querySelector('#dep-ranking');
  const editBanner = container.querySelector('#dep-edit-banner');
  const cancelEditBtn = container.querySelector('#dep-cancel-edit');
  const alumnosFields = container.querySelector('#dep-alumnos-fields');
  const participantesBlock = container.querySelector('#dep-participantes-block');
  const claseALabel = container.querySelector('#dep-alumno-a-clase');
  const claseBLabel = container.querySelector('#dep-alumno-b-clase');
  const pickerA = bindAlumnoPicker(container.querySelector('#dep-alumno-a').closest('[data-alumno-picker]'), { onChange: markDirty });
  const pickerB = bindAlumnoPicker(container.querySelector('#dep-alumno-b').closest('[data-alumno-picker]'), { onChange: markDirty });

  let currentRows = [];
  let participantList = null;
  let editingId = null;

  function modo(){
    return lcDeporteConfig(sport.value)?.modo || 'enfrentamiento';
  }

  function exitEditMode(){
    editingId = null;
    editBanner.style.display = 'none';
  }

  function enterEditMode(row){
    editingId = parseInt(row.id, 10);
    // Asignar .value programáticamente NO dispara el evento change en <select>/<input>
    ta.value = row.equipo_a;
    tb.value = row.equipo_b;
    sa.value = row.marcador_a;
    sb.value = row.marcador_b;
    let parts = [];
    try { parts = JSON.parse(row.participantes) || []; } catch(e) {}
    if (Array.isArray(parts)) participantList.reset(parts.map(p => ({ clase: p.clase, nombre: p.nombre })));
    if (modo() === 'alumnos') {
      claseALabel.textContent = row.equipo_a;
      claseBLabel.textContent = row.equipo_b;
      pickerA.setClase(row.equipo_a, row.alumno_a);
      pickerB.setClase(row.equipo_b, row.alumno_b);
    }
    notas.value = row.notas || '';
    editBanner.style.display = 'flex';
    // Scroll suave al formulario
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function buildParticipantList(){
    const isIndividual = modo() === 'individual';
    participantesLabel.textContent = isIndividual ? 'Participantes y marcas' : 'Participantes (opcional)';
    participantList = createParticipantList({
      container: participantesListEl,
      classes: lcClases(),
      onChange: markDirty,
      extraField: isIndividual ? { name: 'marca', label: 'Marca' } : null,
    });
  }

  [sa, sb].forEach(inp => inp.addEventListener('input', markDirty));
  notas.addEventListener('input', markDirty);
  addParticipanteBtn.addEventListener('click', () => participantList.add());
  bindSteppers(container);

  cancelEditBtn.addEventListener('click', () => {
    exitEditMode();
    sa.value = 0;
    sb.value = 0;
    participantList.reset([]);
    notas.value = '';
    setStatus(msg, '', 'info');
  });

  // Rellena los dos selectores con los alumnos de las clases elegidas.
  async function syncAlumnos(){
    claseALabel.textContent = ta.value || '';
    claseBLabel.textContent = tb.value || '';
    await pickerA.setClase(ta.value);
    await pickerB.setClase(tb.value);
  }

  function toggleFieldsByModo(){
    const m = modo();
    const isIndividual = m === 'individual';
    const isAlumnos = m === 'alumnos';
    enfrentamientoFields.style.display = isIndividual ? 'none' : '';
    alumnosFields.style.display = isAlumnos ? '' : 'none';
    // En modo por alumnos, la lista libre de participantes sobra: el alumno ya
    // queda identificado por su ficha.
    participantesBlock.style.display = isAlumnos ? 'none' : '';
    buildParticipantList();
    if (isAlumnos) syncAlumnos();
  }

  function renderList(){
    if(!currentRows.length){
      list.innerHTML = '<div class="lc-meta">Sin datos esta semana.</div>';
      return;
    }
    const isIndividual = modo() === 'individual';

    if (isIndividual) {
      const row = currentRows[0];
      let participantes = [];
      try { participantes = JSON.parse(row.participantes) || []; } catch(e) { participantes = []; }
      participantes = participantes.slice().sort((x,y) => (y.puntos||0) - (x.puntos||0));
      const rowsHtml = participantes.map(p => `
        <tr>
          <td>${lcEscapar(p.clase)}</td>
          <td>${lcEscapar(p.nombre)}</td>
          <td style="text-align:center">${p.marca}</td>
          <td style="text-align:center">${p.puntos}</td>
        </tr>`).join('');
      list.innerHTML = `
        <div class="lc-table-container">
          <table class="lc-table">
            <thead><tr><th>Clase</th><th>Nombre</th><th>Marca</th><th>Puntos</th></tr></thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>`;
      return;
    }

    const rowsHtml = currentRows.map(r => {
      let participantesText = '';
      if (r.participantes) {
        try {
          const parsed = JSON.parse(r.participantes);
          if (Array.isArray(parsed)) participantesText = parsed.map(p => `${p.clase}: ${p.nombre}`).join(', ');
        } catch (e) {
          participantesText = r.participantes;
        }
      }
      const quien = modo() === 'alumnos'
        ? `${r.alumno_a_nombre || '—'} vs ${r.alumno_b_nombre || '—'}`
        : participantesText;
      return `
        <tr>
          <td>${lcEscapar(r.equipo_a)}</td>
          <td style="text-align:center">${r.marcador_a} – ${r.marcador_b}</td>
          <td>${lcEscapar(r.equipo_b)}</td>
          <td style="text-align:center">${r.pts_a} / ${r.pts_b}</td>
          <td>${lcEscapar(quien)}</td>
          <td>${lcEscapar(r.notas)}</td>
          <td><button type="button" class="lc-btn lc-btn-secondary lc-edit-row-btn lc-btn-xs" data-row-id="${r.id}">Editar</button></td>
        </tr>`;
    }).join('');

    list.innerHTML = `
      <div class="lc-table-container">
        <table class="lc-table">
          <thead>
            <tr>
              <th>Equipo A</th>
              <th>Marcador</th>
              <th>Equipo B</th>
              <th>Puntos A/B</th>
              <th>${modo() === 'alumnos' ? 'Alumnos' : 'Participantes'}</th>
              <th>Notas</th>
              <th></th>
            </tr>
          </thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>`;

    list.querySelectorAll('.lc-edit-row-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const rowId = parseInt(btn.dataset.rowId, 10);
        const row = currentRows.find(r => parseInt(r.id, 10) === rowId);
        if (row) enterEditMode(row);
      });
    });
  }

  function prefillFromTeams(silent = false){
    // Solo en enfrentamiento entre clases: en modo por alumnos cada partido es
    // un registro propio y precargar "el partido de esas dos clases" no aplica.
    if (modo() !== 'enfrentamiento') return;
    const A = ta.value, B = tb.value;
    let row = currentRows.find(r => r.equipo_a===A && r.equipo_b===B);
    if(!row) row = currentRows.find(r => r.equipo_a===B && r.equipo_b===A);
    if(row){
      sa.value = row.equipo_a===A ? row.marcador_a : row.marcador_b;
      sb.value = row.equipo_a===A ? row.marcador_b : row.marcador_a;
      notas.value = row.notas || '';
      if (!silent) setStatus(msg, 'Partido existente cargado', 'info');
    } else if (!silent) {
      setStatus(msg, '', 'info');
    }
  }

  function prefillIndividual(){
    const row = currentRows[0];
    if (!row) { participantList.reset([]); return; }
    let participantes = [];
    try { participantes = JSON.parse(row.participantes) || []; } catch(e) { participantes = []; }
    participantList.reset(participantes.map(p => ({ clase: p.clase, nombre: p.nombre, marca: p.marca })));
    notas.value = row.notas || '';
  }

  async function loadList(){
    setStatus(msg, '', 'info');
    if(!ctx.year || !ctx.week){ return; }
    const dep = sport.value;
    try{
      const q = new URLSearchParams({deporte: dep, year: ctx.year, week: ctx.week});
      const res = await window.LC_get(window.LC_EP + '/deportes?' + q.toString());
      currentRows = res?.partidos || [];
      renderList();
      if (modo() === 'individual') prefillIndividual();
      else prefillFromTeams();
      ctx.onDirtyChange?.(false);
    }catch(e){
      currentRows = [];
      renderList();
      setStatus(msg, 'Error al cargar', 'error');
      console.error('GET /deportes', e);
    }
    loadRanking();
  }

  // Clasificación acumulada (toda la temporada) del deporte seleccionado.
  async function loadRanking(){
    const dep = sport.value;
    rankingLabel.textContent = lcDeporteConfig(dep)?.label || dep;
    rankingEl.innerHTML = 'Cargando…';
    try{
      const res = await window.LC_get(window.LC_EP + '/clasificacion');
      const entry = res?.por_deporte?.find(d => d.value === dep);
      rankingEl.innerHTML = renderRankingConToggle(entry?.ranking, entry?.ranking_alumnos);
      bindRankingToggles(rankingEl);
    }catch(e){
      rankingEl.innerHTML = '<div class="lc-meta">Error al cargar la clasificación.</div>';
      console.error('GET /clasificacion', e);
    }
  }

  // Al cambiar deporte o equipos, salir de modo edición
  sport.addEventListener('change', ()=>{ exitEditMode(); toggleFieldsByModo(); loadList(); });
  ta.addEventListener('change', ()=>{ exitEditMode(); prefillFromTeams(); if (modo() === 'alumnos') syncAlumnos(); });
  tb.addEventListener('change', ()=>{ exitEditMode(); prefillFromTeams(); if (modo() === 'alumnos') syncAlumnos(); });

  toggleFieldsByModo();
  loadList();

  add.addEventListener('click', async ()=>{
    if(!ctx.year || !ctx.week) return setStatus(msg, 'Semana inválida', 'error');

    const m = modo();
    const isIndividual = m === 'individual';
    const isAlumnos = m === 'alumnos';

    if (!isIndividual && ta.value === tb.value) {
      return setStatus(msg, 'Las clases deben ser distintas', 'error');
    }
    if (isAlumnos && (!pickerA.value() || !pickerB.value())) {
      return setStatus(msg, 'Selecciona el alumno de cada clase', 'error');
    }

    const payload = {
      deporte: sport.value,
      year: ctx.year,
      week: ctx.week,
      notas: (notas.value||''),
    };

    if (editingId) payload.id = editingId;

    if (isIndividual) {
      const valores = participantList.getValues();
      if (!valores.length) return setStatus(msg, 'Añade al menos un participante con su marca', 'error');
      for (const p of valores) {
        if (p.marca === undefined || p.marca === '' || isNaN(parseFloat(p.marca))) {
          return setStatus(msg, 'Todas las marcas deben ser números', 'error');
        }
      }
      payload.participantes = JSON.stringify(valores.map(p => ({ clase: p.clase, nombre: p.nombre, marca: p.marca })));
    } else {
      payload.equipo_a = ta.value;
      payload.equipo_b = tb.value;
      payload.marcador_a = parseInt(sa.value||0,10);
      payload.marcador_b = parseInt(sb.value||0,10);
      if (isAlumnos) {
        payload.alumno_a = pickerA.value();
        payload.alumno_b = pickerB.value();
        payload.participantes = '[]';
      } else {
        payload.participantes = JSON.stringify(participantList.getValues());
      }
    }

    setButtonLoading(add, true);
    try{
      const res = await window.LC_post(window.LC_EP + '/deportes', payload);
      setButtonLoading(add, false);
      exitEditMode();
      currentRows = res?.partidos || [];
      renderList();
      setStatus(msg, 'Guardado ✅', 'success');
      ctx.onDirtyChange?.(false);
      if (isIndividual) prefillIndividual();
      else if (isAlumnos) { sa.value = 0; sb.value = 0; notas.value = ''; }
      else { participantList.reset([]); prefillFromTeams(true); }
      loadRanking();
    }catch(e){
      setButtonLoading(add, false);
      setStatus(msg, 'Error al guardar: ' + (e.message||''), 'error');
      console.error('POST /deportes', payload, e);
    }
  });

  return {
    refresh(newCtx){ ctx = newCtx; exitEditMode(); loadList(); }
  };
}
