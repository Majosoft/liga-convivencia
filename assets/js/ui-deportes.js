// UI Deportes
const LC_EQUIPOS = ["1ºA","1ºB","2ºA","2ºB","3ºA","3ºB","4ºA","4ºB"];
const LC_DEPORTES = [
  {value:"futbol", label:"Fútbol"},
  {value:"baloncesto", label:"Baloncesto"},
  {value:"pinpon", label:"Pin Pon"},
  {value:"matapollos", label:"Matapollos"},
];

function uiDeportes(){
  return `
    <div style="margin-top:16px">
      <h4 class="lc-title" style="font-size:18px">Deportes – registro de partidos</h4>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px;font-family:'Enriqueta',serif;">Deporte</label>
        <select id="dep-sport">
          <option value="futbol">Fútbol</option>
          <option value="baloncesto">Baloncesto</option>
          <option value="pinpon">Pin Pon</option>
          <option value="matapollos">Matapollos</option>
        </select>
      </div>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Semana</label>
        <select id="dep-week"></select>
      </div>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Partido</label>
        <select id="dep-team-a">
          <option>1ºA</option><option>1ºB</option><option>2ºA</option><option>2ºB</option>
          <option>3ºA</option><option>3ºB</option><option>4ºA</option><option>4ºB</option>
        </select>
        <span class="lc-meta">vs</span>
        <select id="dep-team-b">
          <option>1ºA</option><option>1ºB</option><option>2ºA</option><option>2ºB</option>
          <option>3ºA</option><option>3ºB</option><option>4ºA</option><option>4ºB</option>
        </select>
      </div>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Marcador</label>
        <input type="number" id="dep-score-a" value="0" min="0"/>
        <span class="lc-meta">–</span>
        <input type="number" id="dep-score-b" value="0" min="0"/>
      </div>

      <!-- Participantes para Pin Pon -->
      <div id="dep-participantes-pinpon" style="display:none">
        <div class="lc-field">
          <label class="lc-meta" style="min-width:110px">Participantes</label>
          <button type="button" id="dep-add-participante" class="lc-btn ghost" style="margin-left:8px">+ Añadir</button>
        </div>
        
        <div id="dep-participantes-list" style="margin:8px 0">
          <!-- Se llenará dinámicamente -->
        </div>
      </div>

      <div class="lc-field">
        <label class="lc-meta" style="min-width:110px">Notas</label>
        <textarea id="dep-notas" placeholder="Observaciones"></textarea>
      </div>

      <div style="margin-top:12px">
        <h5 class="lc-title" style="font-size:16px;margin:0">Partidos guardados</h5>
        <div id="dep-list" class="lc-meta">Sin partidos esta semana.</div>
      </div>

      <div style="display: flex; justify-content:center; margin:30px;" >
        <div class="lc-field" style="justify-content:flex-start">
          <button class="lc-btn primary" id="dep-add" type="button" style="background-color: green">Guardar partido</button>
          <span id="dep-msg" class="lc-meta"></span>
        </div>
      </div>

    </div>
  `;
}

function bindDeportes(container){
  const sport = container.querySelector('#dep-sport');
  const week  = container.querySelector('#dep-week');
  const ta    = container.querySelector('#dep-team-a');
  const tb    = container.querySelector('#dep-team-b');
  const sa    = container.querySelector('#dep-score-a');
  const sb    = container.querySelector('#dep-score-b');
  const notas = container.querySelector('#dep-notas');
  const add   = container.querySelector('#dep-add');
  const msg   = container.querySelector('#dep-msg');
  const list  = container.querySelector('#dep-list');
  const participantesDiv = container.querySelector('#dep-participantes-pinpon');
  const addParticipante = container.querySelector('#dep-add-participante');
  const participantesList = container.querySelector('#dep-participantes-list');

  let currentRows = []; // partidos de la semana/deporte actual
  let participantes = []; // array de participantes {clase, nombre}

  const setMsg = (t, bad=false)=>{ msg.textContent = t||''; msg.style.color = bad ? '#b91c1c' : '#6b7280'; };

  // Función para crear un input de participante
  function createParticipanteInput(index, participante = {clase: '', nombre: ''}) {
    return `
      <div class="lc-field" style="margin:4px 0; padding:8px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb">
        <select class="dep-participante-clase" style="width:80px">
          <option value="1ºA" ${participante.clase === '1ºA' ? 'selected' : ''}>1ºA</option>
          <option value="1ºB" ${participante.clase === '1ºB' ? 'selected' : ''}>1ºB</option>
          <option value="2ºA" ${participante.clase === '2ºA' ? 'selected' : ''}>2ºA</option>
          <option value="2ºB" ${participante.clase === '2ºB' ? 'selected' : ''}>2ºB</option>
          <option value="3ºA" ${participante.clase === '3ºA' ? 'selected' : ''}>3ºA</option>
          <option value="3ºB" ${participante.clase === '3ºB' ? 'selected' : ''}>3ºB</option>
          <option value="4ºA" ${participante.clase === '4ºA' ? 'selected' : ''}>4ºA</option>
          <option value="4ºB" ${participante.clase === '4ºB' ? 'selected' : ''}>4ºB</option>
        </select>
        <input type="text" class="dep-participante-nombre" placeholder="Nombre del participante" 
               value="${participante.nombre || ''}" style="flex:1; margin:0 8px"/>
        <button type="button" class="dep-remove-participante lc-btn ghost" style="color:#b91c1c; padding:4px 8px">×</button>
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
    participantesList.querySelectorAll('.dep-remove-participante').forEach((btn, index) => {
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

  // Función para mostrar/ocultar participantes según el deporte
  function toggleParticipantes() {
    const isPinpon = sport.value === 'pinpon';
    participantesDiv.style.display = isPinpon ? 'block' : 'none';
  }

  // Rellenar semanas de la temporada (como en Semáforo)
  (function initWeeks(){
    const weeks = seasonWeeksFor(new Date());
    week.innerHTML = weeks.map(w => `<option value="${w.value}">${w.label}</option>`).join('');
    const {year, week:ww} = currentISOWeek();
    const nowISO = `${year}-W${String(ww).padStart(2,'0')}`;
    const opt = Array.from(week.options).find(o=>o.value===nowISO);
    if(opt) week.value = nowISO;
  })();

  function renderList(){
    if(!currentRows.length){
      list.innerHTML = '<div class="lc-meta">Sin partidos esta semana.</div>';
      return;
    }
    const rowsHtml = currentRows.map(r => {
      // Parsear participantes si vienen como JSON (solo para Pin Pon)
      let participantesText = '';
      if (r.participantes) {
        try {
          const parsed = JSON.parse(r.participantes);
          if (Array.isArray(parsed)) {
            participantesText = parsed.map(p => `${p.clase}: ${p.nombre}`).join(', ');
          }
        } catch (e) {
          // Si no es JSON, usar como texto
          participantesText = r.participantes;
        }
      }
      
      return `
        <tr>
          <td>${r.equipo_a}</td>
          <td style="text-align:center">${r.marcador_a} – ${r.marcador_b}</td>
          <td>${r.equipo_b}</td>
          <td style="text-align:center">${r.pts_a} / ${r.pts_b}</td>
          <td>${participantesText ? participantesText : ''}</td>
          <td>${r.notas ? r.notas : ''}</td>
        </tr>`;
    }).join('');
    
    list.innerHTML = `
      <div class="lc-field" style="margin:8px 0 0; display:block">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="text-align:left">Equipo A</th>
              <th style="text-align:center">Marcador</th>
              <th style="text-align:left">Equipo B</th>
              <th style="text-align:center">Puntos A/B</th>
              <th style="text-align:left">Participantes</th>
              <th style="text-align:left">Notas</th>
            </tr>
          </thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>`;
  }

  function prefillFromTeams(){
    const A = ta.value, B = tb.value;
    // busca A-B o B-A
    let row = currentRows.find(r => r.equipo_a===A && r.equipo_b===B);
    if(!row) row = currentRows.find(r => r.equipo_a===B && r.equipo_b===A);
    if(row){
      sa.value = row.equipo_a===A ? row.marcador_a : row.marcador_b;
      sb.value = row.equipo_a===A ? row.marcador_b : row.marcador_a;
      notas.value = row.notas || '';
      setMsg('Partido existente cargado');
    } else {
      setMsg('');
    }
  }

  async function loadList(){
    setMsg('');
    const wk = parseWeekInput(week.value);
    if(!wk){ setMsg('Semana inválida', true); return; }
    const dep = sport.value;
    try{
      const q = new URLSearchParams({deporte: dep, year: wk.year, week: wk.week});
      const res = await window.LC_get(window.LC_EP + '/deportes?' + q.toString());
      currentRows = res?.partidos || [];
      renderList();
      prefillFromTeams(); // intenta rellenar si ya hay equipos elegidos
    }catch(e){
      currentRows = [];
      renderList();
      setMsg('Error al cargar partidos', true);
      console.error('GET /deportes', e);
    }
  }

  // Event listeners
  addParticipante.addEventListener('click', addParticipanteHandler);
  sport.addEventListener('change', ()=>{ toggleParticipantes(); loadList(); });
  week.addEventListener('change', loadList);
  ta.addEventListener('change', prefillFromTeams);
  tb.addEventListener('change', prefillFromTeams);

  // Carga inicial
  toggleParticipantes();
  loadList();

  // Guardar partido
  add.addEventListener('click', async ()=>{
    setMsg('');
    const wk = parseWeekInput(week.value);
    if(!wk) return setMsg('Semana inválida', true);
    if(ta.value === tb.value) return setMsg('Los equipos deben ser distintos', true);

    const payload = {
      deporte: sport.value,
      year: wk.year,
      week: wk.week,
      equipo_a: ta.value,
      equipo_b: tb.value,
      marcador_a: parseInt(sa.value||0,10),
      marcador_b: parseInt(sb.value||0,10),
      notas: (notas.value||'')
    };

    // Añadir participantes solo para Pin Pon
    if (sport.value === 'pinpon') {
      const participantesData = [];
      participantesList.querySelectorAll('.lc-field').forEach(row => {
        const clase = row.querySelector('.dep-participante-clase')?.value;
        const nombre = row.querySelector('.dep-participante-nombre')?.value?.trim();
        if (clase && nombre) {
          participantesData.push({clase, nombre});
        }
      });
      payload.participantes = JSON.stringify(participantesData);
    }

    try{
      // el servidor devuelve la lista actualizada
      const res = await window.LC_post(window.LC_EP + '/deportes', payload);
      currentRows = res?.partidos || [];
      renderList();
      setMsg('Partido guardado ✅');
      // Limpiar participantes
      participantes = [];
      renderParticipantes();
      // NO reseteamos los inputs: prefilleamos el partido recién guardado
      prefillFromTeams();
    }catch(e){
      setMsg('Error al guardar: ' + (e.message||''), true);
      console.error('POST /deportes', payload, e);
    }
  });
}
