// UI Partes
function uiPartes(){
  return `
    <div style="margin-top:16px">
      <h4 class="lc-title" style="font-size:18px">Partes – registro semanal</h4>

      <div class="lc-field">
        <label for="pt-clase" class="lc-meta" style="min-width:110px">Clase/Grupo</label>
        <select id="pt-clase">
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
        <label for="pt-week" class="lc-meta" style="min-width:110px">Semana</label>
        <select id="pt-week"></select>
        <div class="lc-meta" id="pt-compweek-label"></div>
      </div>

      <div class="lc-field">
        <label for="pt-count" class="lc-meta" style="min-width:110px">Nº de partes</label>
        <input type="number" id="pt-count" value="0" min="0"/>
      </div>

      <div class="lc-field">
        <label for="pt-notas" class="lc-meta" style="min-width:110px">Notas</label>
        <textarea id="pt-notas" placeholder="Observaciones"></textarea>
      </div>

      <div style="display:flex; align-items:center; justify-content:center; margin:24px 0">
        <div class="lc-field" style="justify-content:flex-start">
          <button class="lc-btn primary" id="pt-save" type="button" style="background-color:green;border-radius:6px;width:auto">Guardar</button>
          <span id="pt-msg" class="lc-meta"></span>
        </div>
      </div>
    </div>
  `;
}

function bindPartes(container){
  const clase = container.querySelector('#pt-clase');
  const week  = container.querySelector('#pt-week');
  const count = container.querySelector('#pt-count');
  const notas = container.querySelector('#pt-notas');
  const btn   = container.querySelector('#pt-save');
  const msg   = container.querySelector('#pt-msg');
  const label = container.querySelector('#pt-compweek-label');

  // rellenar semanas de temporada (reutilizamos seasonWeeksFor del plugin)
  (function initWeeks(){
    const w = seasonWeeksFor(new Date());
    week.innerHTML = w.map(x => `<option value="${x.value}">${x.label}</option>`).join('');
    // Selección por defecto = semana ISO actual si cae en temporada
    const today = new Date();
    const d = new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()));
    const dayNr = (d.getUTCDay() + 6) % 7; d.setUTCDate(d.getUTCDate() - dayNr + 3);
    const firstThu = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
    const dayDiff = (d - firstThu)/86400000;
    const wnum = 1 + Math.floor(dayDiff/7);
    const nowISO = `${d.getUTCFullYear()}-W${String(wnum).padStart(2,'0')}`;
    const opt = Array.from(week.options).find(o => o.value === nowISO);
    if (opt) week.value = nowISO;
  })();

  function setMsg(t,bad=false){ msg.textContent=t||''; msg.style.color = bad ? '#b91c1c' : '#6b7280'; }

  function updateLabel(){
    const monday = dateFromWeekInput(week.value);
    if(!monday){ label.textContent=''; return; }
    const cw = compWeekOf(monday);
    const season = `${cw.seasonYear}-${String(cw.seasonYear+1).slice(-2)}`;
    label.textContent = `Semana de competición: ${cw.week} · Temporada ${season}`;
  }

  async function loadData(){
    setMsg('');
    const wk = parseWeekInput(week.value);
    const cls = (clase.value||'').trim();
    if(!cls || !wk){ return; }
    try {
      const q = new URLSearchParams({clase:cls, year:wk.year, week:wk.week});
      const res = await window.LC_get(window.LC_EP + '/partes?' + q.toString());
      if(res && res.ok && res.data){
        count.value = res.data.partes ?? 0;
        notas.value = res.data.notas ?? '';
        setMsg('Datos cargados');
      } else {
        count.value = 0;
        notas.value = '';
        setMsg('Nueva semana');
      }
    } catch(e){
      setMsg('Error al cargar', true);
      console.error('GET /partes', e);
    }
  }

  week.addEventListener('change', ()=>{ updateLabel(); loadData(); });
  clase.addEventListener('change', loadData);

  // inicial
  updateLabel();
  loadData();

  btn.addEventListener('click', async ()=>{
    setMsg('');
    const wk = parseWeekInput(week.value);
    const cls = (clase.value||'').trim();
    if(!cls)  return setMsg('Indica la clase', true);
    if(!wk)   return setMsg('Semana inválida', true);

    const payload = {
      clase: cls, year:wk.year, week:wk.week,
      partes: parseInt(count.value||0, 10),
      notas: (notas.value||'')
    };
    try{
      const res = await window.LC_post(window.LC_EP + '/partes', payload);
      if(res && res.ok){
        setMsg('Guardado ✅');
      } else {
        setMsg('Error al guardar', true);
      }
    }catch(e){
      setMsg('Error al guardar', true);
      console.error('POST /partes', payload, e);
    }
  });
}
