// UI Semáforo
function uiSemaforo(){
  return `
    <div style="margin-top:16px">
      <h4 class="lc-title" style="font-size:18px">Semáforo – registro semanal</h4>

      <div style="display:flex; flex-direction:column;">
        <div class="lc-field">
          <label for="sf-clase" class="lc-meta" style="min-width:110px">Clase/Grupo</label>
          <select id="sf-clase">
            <option value="1ºA" selected>1ºA</option>
            <option value="1ºB">1ºB</option>
            <option value="2ºA">2ºA</option>
            <option value="2ºB">2ºB</option>
            <option value="3ºA">3ºA</option>
            <option value="3ºB">3ºB</option>
            <option value="4ºA">4ºA</option>
            <option value="4ºB">4ºB</option>
          </select>
        </div>

        <div class="lc-field">
          <label for="sf-week" class="lc-meta" style="min-width:110px">Semana</label>
          <select id="sf-week"></select>
        </div>
        <div class="lc-field">
            <div>
                <label for="sf-verdes" class="lc-meta">🟢 Gomets Verdes (+1)</label>
                <input type="number" id="sf-verdes" value="0" min="0"/>
            </div>
            <div>
                <label for="sf-amarillos" class="lc-meta">🟡 Gomets Amarillos (-1)</label>
                <input type="number" id="sf-amarillos" value="0" min="0"/>
            </div>
            <div>
                <label for="sf-rojos" class="lc-meta">🔴 Gomets Rojos (-3)</label>
                <input type="number" id="sf-rojos" value="0" min="0"/>
            </div>
        </div>  
        <div class="lc-field">
          <label for="sf-total" class="lc-meta" style="min-width:110px">Total actual</label>
          <input type="number" id="sf-total" value="0" disabled/>
        </div>

        <div style="display:flex">
            <label for="sf-notas" class="lc-meta" style="min-width:110px">Notas</label>
            <textarea id="sf-notas" placeholder="Observaciones"></textarea>
        </div>

        <div class="lc-field" style="display: flex; flex-direction: column; justify-content:center; text-align: center; margin:50px">
          
          <span id="sf-msg" class="lc-meta" style="margin:10px"></span>
          <div>
              <button class="lc-btn primary" id="sf-save" type="button"
              style="background-color: green; border-radius:5px; flex:0 0 auto;">
            Guardar
           </button>
          </div>
        </div>
      </div>
    </div>`;
}

function bindSemaforo(container){
  const clase = container.querySelector('#sf-clase');
  const week  = container.querySelector('#sf-week');   // <select> "YYYY-Www"
  const verdes = container.querySelector('#sf-verdes');
  const amarillos = container.querySelector('#sf-amarillos');
  const rojos = container.querySelector('#sf-rojos');
  const total = container.querySelector('#sf-total');  // "Total actual"
  const notas = container.querySelector('#sf-notas');
  const btnSave = container.querySelector('#sf-save');
  const msg   = container.querySelector('#sf-msg');

  // Rellenar semanas (octubre->junio)
  (function initCompWeeks(){
    const weeks = seasonWeeksFor(new Date());
    week.innerHTML = weeks.map(w => `<option value="${w.value}">${w.label}</option>`).join('');

    // Seleccionar la ISO de HOY si cae en temporada
    const today = new Date();
    const d = new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()));
    const dayNr = (d.getUTCDay() + 6) % 7; d.setUTCDate(d.getUTCDate() - dayNr + 3);
    const firstThu = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
    const dayDiff = (d - firstThu)/86400000;
    const w = 1 + Math.floor(dayDiff/7);
    const nowISO = `${d.getUTCFullYear()}-W${String(w).padStart(2,'0')}`;
    const opt = Array.from(week.options).find(o => o.value === nowISO);
    if (opt) week.value = nowISO; // si no coincide (verano), primera opción
  })();

  const setMsg = (t, bad=false)=>{ if(!msg) return; msg.textContent = t||''; msg.style.color = bad ? '#b91c1c' : '#6b7280'; };

  // Carga de datos para (clase, semana)
  async function loadData(){
    setMsg('');
    const wk  = parseWeekInput(week.value);
    const cls = (clase.value||'').trim();
    if(!cls){ setMsg('Indica la clase', true); return; }
    if(!wk){  setMsg('Semana inválida', true); return; }

    const q = new URLSearchParams({clase: cls, year: wk.year, week: wk.week});
    try{
      const res = await window.LC_get(window.LC_EP + '/semaforo?' + q.toString());
      total.value = res.current_total ?? 0; // total actual

      if(res.data){
        verdes.value = res.data.gomets_verdes ?? 0;
        amarillos.value = res.data.gomets_amarillos ?? 0;
        rojos.value = res.data.gomets_rojos ?? 0;
        notas.value = res.data.notas ?? '';
        updateTotal(); // Calcular total
        setMsg('Datos cargados');
      } else {
        verdes.value = 0;
        amarillos.value = 0;
        rojos.value = 0;
        notas.value = '';
        updateTotal(); // Calcular total
        setMsg('Nueva semana: introduce los gomets de esta semana');
      }
    }catch(e){
      setMsg('Error al cargar', true);
      console.error('GET /semaforo', e);
    }
  }

  // Auto: al cambiar SEMANA o CLASE → actualizar etiqueta y cargar
  week.addEventListener('change', ()=>{ loadData(); });
  clase.addEventListener('change', loadData);

  // Auto: calcular total cuando cambien los gomets
  function updateTotal() {
    const ver = parseInt(verdes.value || 0, 10);
    const ama = parseInt(amarillos.value || 0, 10);
    const roj = parseInt(rojos.value || 0, 10);
    
    // Total = (verdes * 1) - (amarillos * 1) - (rojos * 3)
    total.value = (ver * 1) - (ama * 1) - (roj * 3);
  }
  
  verdes.addEventListener('input', updateTotal);
  amarillos.addEventListener('input', updateTotal);
  rojos.addEventListener('input', updateTotal);

  // Inicial
  loadData();

  // Guardar (upsert)
  btnSave.addEventListener('click', async ()=>{
    setMsg('');
    const wk  = parseWeekInput(week.value);
    const cls = (clase.value||'').trim();
    if(!cls){ return setMsg('Indica la clase', true); }
    if(!wk){  return setMsg('Semana inválida', true); }

    const payload = {
      clase: cls, year: wk.year, week: wk.week,
      gomets_verdes: parseInt(verdes.value||0, 10),
      gomets_amarillos: parseInt(amarillos.value||0, 10),
      gomets_rojos: parseInt(rojos.value||0, 10),
      notas: (notas.value||'')
    };

    try{
      const res = await window.LC_post(window.LC_EP + '/semaforo', payload);
      if (res && res.ok) {
        total.value = res.current_total ?? 0; // refresca total actual tras guardar
        setMsg('Guardado ✅');
      } else {
        setMsg('Error: ' + (res?.error || 'Respuesta inválida'), true);
        console.error('POST /semaforo response:', res);
      }
    }catch(e){
      setMsg('Error al guardar: ' + (e.message || 'Error de conexión'), true);
      console.error('POST /semaforo', payload, e);
    }
  });
}
