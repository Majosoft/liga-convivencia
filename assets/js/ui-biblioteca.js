// UI Biblioteca
const LC_ACTIVIDADES = [
  {value:"podcast", label:"Podcast"},
  {value:"traduccion", label:"Traducción"},
  {value:"ajedrez", label:"Ajedrez"},
  {value:"mitos", label:"Mitos"}
];

const LC_CLASES = ["1ºA","1ºB","2ºA","2ºB","3ºA","3ºB","4ºA","4ºB"];

function uiBiblioteca(){
  return `
    <div style="margin-top:16px">
      <h4 class="lc-title" style="font-size:18px">Biblioteca – registro de actividades</h4>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Actividad</label>
        <select id="bib-actividad">
          <option value="podcast">Podcast</option>
          <option value="traduccion">Traducción</option>
          <option value="ajedrez">Ajedrez</option>
          <option value="mitos">Mitos</option>
        </select>
      </div>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Semana</label>
        <select id="bib-week"></select>
      </div>

      <!-- Solo para Ajedrez: enfrentamiento entre clases -->
      <div id="bib-ajedrez" class="lc-field" style="display:none">
        <label class="lc-meta" style="min-width:110px">Partida</label>
        <select id="bib-clase-a">
          <option>1ºA</option><option>1ºB</option><option>2ºA</option><option>2ºB</option>
          <option>3ºA</option><option>3ºB</option><option>4ºA</option><option>4ºB</option>
        </select>
        <span class="lc-meta">vs</span>
        <select id="bib-clase-b">
          <option>1ºA</option><option>1ºB</option><option>2ºA</option><option>2ºB</option>
          <option>3ºA</option><option>3ºB</option><option>4ºA</option><option>4ºB</option>
        </select>
      </div>

      <!-- Para todas las actividades: participantes dinámicos -->
      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Participantes</label>
        <button type="button" id="bib-add-participante" class="lc-btn ghost" style="margin-left:8px">+ Añadir</button>
      </div>
      
      <div id="bib-participantes-list" style="margin:8px 0">
        <!-- Se llenará dinámicamente -->
      </div>

      <!-- Solo para Ajedrez: resultado del enfrentamiento -->
      <div id="bib-resultado-ajedrez" class="lc-field" style="display:none">
        <label class="lc-meta" style="min-width:110px">Resultado</label>
        <select id="bib-ganador">
          <option value="empate">Empate</option>
          <option value="clase-a">Gana Clase A</option>
          <option value="clase-b">Gana Clase B</option>
        </select>
      </div>

      <!-- Para todas las actividades: valoración -->
      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Valoración</label>
        <select id="bib-valoracion">
          <option value="3">Excelente (3 puntos)</option>
          <option value="1">Regular (1 punto)</option>
          <option value="0">Insuficiente (0 puntos)</option>
        </select>
      </div>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Notas</label>
        <textarea id="bib-notas" placeholder="Observaciones"></textarea>
      </div>

      <div style="display: flex; justify-content:center; margin:50px;" >
        <div class="lc-field" style="justify-content:flex-start">
          <button class="lc-btn primary" id="bib-add" type="button" style="background-color: green">Guardar actividad</button>
          <span id="bib-msg" class="lc-meta"></span>
        </div>
      </div>

      <div style="margin-top:12px">
        <h5 class="lc-title" style="font-size:16px;margin:0">Actividades registradas</h5>
        <div id="bib-list" class="lc-meta">Sin actividades esta semana.</div>
      </div>
    </div>
  `;
}

function bindBiblioteca(container){
  const actividad = container.querySelector('#bib-actividad');
  const week  = container.querySelector('#bib-week');
  const claseA = container.querySelector('#bib-clase-a');
  const claseB = container.querySelector('#bib-clase-b');
  const ganador = container.querySelector('#bib-ganador');
  const valoracion = container.querySelector('#bib-valoracion');
  const notas = container.querySelector('#bib-notas');
  const add   = container.querySelector('#bib-add');
  const msg   = container.querySelector('#bib-msg');
  const list  = container.querySelector('#bib-list');
  const ajedrezDiv = container.querySelector('#bib-ajedrez');
  const resultadoDiv = container.querySelector('#bib-resultado-ajedrez');
  const addParticipante = container.querySelector('#bib-add-participante');
  const participantesList = container.querySelector('#bib-participantes-list');

  let currentRows = []; // actividades de la semana actual
  let participantes = []; // array de participantes {clase, nombre}

  const setMsg = (t, bad=false)=>{ msg.textContent = t||''; msg.style.color = bad ? '#b91c1c' : '#6b7280'; };

  // Función para crear un input de participante
  function createParticipanteInput(index, participante = {clase: '', nombre: ''}) {
    return `
      <div class="lc-field" style="margin:4px 0; padding:8px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb">
        <select class="bib-participante-clase" style="width:80px">
          <option value="1ºA" ${participante.clase === '1ºA' ? 'selected' : ''}>1ºA</option>
          <option value="1ºB" ${participante.clase === '1ºB' ? 'selected' : ''}>1ºB</option>
          <option value="2ºA" ${participante.clase === '2ºA' ? 'selected' : ''}>2ºA</option>
          <option value="2ºB" ${participante.clase === '2ºB' ? 'selected' : ''}>2ºB</option>
          <option value="3ºA" ${participante.clase === '3ºA' ? 'selected' : ''}>3ºA</option>
          <option value="3ºB" ${participante.clase === '3ºB' ? 'selected' : ''}>3ºB</option>
          <option value="4ºA" ${participante.clase === '4ºA' ? 'selected' : ''}>4ºA</option>
          <option value="4ºB" ${participante.clase === '4ºB' ? 'selected' : ''}>4ºB</option>
        </select>
        <input type="text" class="bib-participante-nombre" placeholder="Nombre del participante" 
               value="${participante.nombre || ''}" style="flex:1; margin:0 8px"/>
        <button type="button" class="bib-remove-participante lc-btn ghost" style="color:#b91c1c; padding:4px 8px">×</button>
      </div>
    `;
  }

  // Función para renderizar la lista de participantes
  function renderParticipantes() {
    if (participantes.length === 0) {
      participantesList.innerHTML = '<div class="lc-meta" style="text-align:center; padding:16px; color:#6b7280">No hay participantes añadidos</div>';
      return;
    }
    
    participantesList.innerHTML = participantes.map((p, index) => createParticipanteInput(index, p)).join('');
    
    // Añadir event listeners a los nuevos elementos
    participantesList.querySelectorAll('.bib-remove-participante').forEach((btn, index) => {
      btn.addEventListener('click', () => {
        participantes.splice(index, 1);
        renderParticipantes();
      });
    });
  }

  // Función para añadir un participante
  function addParticipanteHandler() {
    participantes.push({clase: '1ºA', nombre: ''});
    renderParticipantes();
  }

  // Rellenar semanas de la temporada
  (function initWeeks(){
    const weeks = seasonWeeksFor(new Date());
    week.innerHTML = weeks.map(w => `<option value="${w.value}">${w.label}</option>`).join('');
    const {year, week:ww} = currentISOWeek();
    const nowISO = `${year}-W${String(ww).padStart(2,'0')}`;
    const opt = Array.from(week.options).find(o=>o.value===nowISO);
    if(opt) week.value = nowISO;
  })();

  // Mostrar/ocultar campos según la actividad
  function toggleAjedrezFields(){
    const isAjedrez = actividad.value === 'ajedrez';
    ajedrezDiv.style.display = isAjedrez ? 'flex' : 'none';
    resultadoDiv.style.display = isAjedrez ? 'flex' : 'none';
  }

  function renderList(){
    if(!currentRows.length){
      list.innerHTML = '<div class="lc-meta">Sin actividades esta semana.</div>';
      return;
    }
    const rowsHtml = currentRows.map(r => {
      // Parsear participantes si vienen como JSON
      let participantesText = r.participantes || '';
      try {
        const parsed = JSON.parse(r.participantes);
        if (Array.isArray(parsed)) {
          participantesText = parsed.map(p => `${p.clase}: ${p.nombre}`).join(', ');
        }
      } catch (e) {
        // Si no es JSON, usar como texto
      }
      
      return `
        <tr>
          <td>${r.actividad}</td>
          <td>${participantesText}</td>
          <td style="text-align:center">${r.puntos}</td>
          <td>${r.notas ? r.notas : ''}</td>
        </tr>`;
    }).join('');
    
    list.innerHTML = `
      <div class="lc-field" style="margin:8px 0 0; display:block">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="text-align:left">Actividad</th>
              <th style="text-align:left">Participantes</th>
              <th style="text-align:center">Puntos</th>
              <th style="text-align:left">Notas</th>
            </tr>
          </thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>`;
  }

  async function loadList(){
    setMsg('');
    const wk = parseWeekInput(week.value);
    if(!wk){ setMsg('Semana inválida', true); return; }
    const act = actividad.value;
    try{
      const q = new URLSearchParams({actividad: act, year: wk.year, week: wk.week});
      const res = await window.LC_get(window.LC_EP + '/biblioteca?' + q.toString());
      currentRows = res?.actividades || [];
      renderList();
    }catch(e){
      currentRows = [];
      renderList();
      setMsg('Error al cargar actividades', true);
      console.error('GET /biblioteca', e);
    }
  }

  // Event listeners
  addParticipante.addEventListener('click', addParticipanteHandler);
  actividad.addEventListener('change', ()=>{ toggleAjedrezFields(); loadList(); });
  week.addEventListener('change', loadList);

  // Carga inicial
  toggleAjedrezFields();
  loadList();

  // Guardar actividad
  add.addEventListener('click', async ()=>{
    setMsg('');
    const wk = parseWeekInput(week.value);
    if(!wk) return setMsg('Semana inválida', true);
    
    const act = actividad.value;
    const valoracion_val = parseInt(valoracion.value, 10);
    const notas_val = notas.value.trim();

    // Recopilar datos de participantes
    const participantesData = [];
    participantesList.querySelectorAll('.lc-field').forEach(row => {
      const clase = row.querySelector('.bib-participante-clase')?.value;
      const nombre = row.querySelector('.bib-participante-nombre')?.value?.trim();
      if (clase && nombre) {
        participantesData.push({clase, nombre});
      }
    });

    // Validaciones específicas
    if(participantesData.length === 0) return setMsg('Añade al menos un participante', true);
    
    if(act === 'ajedrez') {
      if(claseA.value === claseB.value) return setMsg('Las clases deben ser distintas', true);
    }

    const payload = {
      actividad: act,
      year: wk.year,
      week: wk.week,
      participantes: JSON.stringify(participantesData),
      puntos: valoracion_val,
      notas: notas_val
    };

    // Añadir datos específicos del ajedrez
    if(act === 'ajedrez') {
      payload.clase_a = claseA.value;
      payload.clase_b = claseB.value;
      payload.ganador = ganador.value;
    }

    try{
      const res = await window.LC_post(window.LC_EP + '/biblioteca', payload);
      if(res && res.ok){
        currentRows = res?.actividades || [];
        renderList();
        setMsg('Actividad guardada ✅');
        // Limpiar formulario
        participantes = [];
        renderParticipantes();
        notas.value = '';
      } else {
        setMsg('Error al guardar: ' + (res?.error || 'Error desconocido'), true);
      }
    }catch(e){
      setMsg('Error al guardar: ' + (e.message||''), true);
      console.error('POST /biblioteca', payload, e);
    }
  });
}
