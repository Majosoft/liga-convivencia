// UI Tiempo de Aprendizaje
// Módulo mensual: el profesor registra los puntos de una clase en un mes concreto.
// No depende del contexto semanal del panel (needsClase: false).
// Los datos se almacenan en lc_tiempo_aprendizaje (1 fila por clase + mes).

function uiTiempoAprendizaje(){
  const claseOptions = lcClases().map(c => `<option>${lcEscapar(c)}</option>`).join('');
  const meses = seasonMonthsFor(new Date());
  const mesOptions = meses.map(m => `<option value="${m.value}">${m.label}</option>`).join('');

  return `
    <div>
      <h4 class="lc-module-title">Tiempo de Aprendizaje</h4>
      <p class="lc-module-intro">Registra los puntos mensuales por clase. Cuanto menos se use el ticket de baño, más puntos obtiene la clase.</p>

      <div class="lc-module-split">
      <div class="lc-module-form">

      <div class="lc-form-grid">
        <div class="lc-field">
          <label for="ta-mes">Mes</label>
          <select id="ta-mes">${mesOptions}</select>
        </div>

        <div class="lc-field">
          <label for="ta-clase">Clase</label>
          <select id="ta-clase">${claseOptions}</select>
        </div>

        <div class="lc-field">
          <label for="ta-puntos">Puntos</label>
          <input type="number" id="ta-puntos" value="0" min="0" inputmode="numeric"/>
        </div>
      </div>

      <div class="lc-actions">
        <button class="lc-btn lc-btn-primary" id="ta-guardar" type="button">Guardar</button>
        <button class="lc-btn lc-btn-danger" id="ta-borrar" type="button">Borrar mes</button>
        <span id="ta-msg" class="lc-status" aria-live="polite"></span>
      </div>

      </div>

      <div class="lc-module-side">
      <div class="lc-panel-section">
        <h5 class="lc-section-title">Clasificación – Tiempo de Aprendizaje</h5>
        <div id="ta-ranking" class="lc-meta">Cargando…</div>
      </div>
      </div>

      </div>
    </div>
  `;
}

function bindTiempoAprendizaje(container, ctx){
  const mesSel    = container.querySelector('#ta-mes');
  const claseSel  = container.querySelector('#ta-clase');
  const puntosInp = container.querySelector('#ta-puntos');
  const guardarBtn = container.querySelector('#ta-guardar');
  const borrarBtn  = container.querySelector('#ta-borrar');
  const msg        = container.querySelector('#ta-msg');
  const rankingEl  = container.querySelector('#ta-ranking');

  // Seleccionar el mes actual por defecto
  const hoy = new Date();
  const mesActual = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
  if (mesSel.querySelector(`option[value="${mesActual}"]`)) {
    mesSel.value = mesActual;
  }

  // Parsea el valor del select de mes → {year, month}
  function parseMes(){
    const [y, m] = (mesSel.value || '').split('-');
    return { year: parseInt(y, 10), month: parseInt(m, 10) };
  }

  // Carga los puntos del registro actual (clase + mes seleccionados).
  // Cada carga lleva un número: si llega tarde (ya se cambió de clase o mes, o
  // el profesor ya escribió los puntos), se descarta. Antes la respuesta lenta
  // machacaba con 0 lo recién escrito y se acababa guardando 0 en esa clase.
  let cargaActual = 0;
  let editado = false;
  puntosInp.addEventListener('input', () => { editado = true; });

  async function loadRecord(){
    const { year, month } = parseMes();
    const clase = claseSel.value;
    if (!clase || !year || !month) return;

    const miCarga = ++cargaActual;
    editado = false;
    puntosInp.value = '';
    puntosInp.placeholder = 'Cargando…';
    setStatus(msg, '', 'info');
    try {
      const q = new URLSearchParams({ clase, year, month });
      const res = await window.LC_get(window.LC_EP + '/tiempo-aprendizaje?' + q.toString());
      if (miCarga !== cargaActual) return;
      puntosInp.placeholder = '';
      if (res && res.ok) {
        if (!editado) puntosInp.value = res.puntos || 0;
        if (!res.exists) setStatus(msg, `${clase}: sin datos para este mes`, 'info');
      }
    } catch(e) {
      if (miCarga !== cargaActual) return;
      puntosInp.placeholder = '';
      setStatus(msg, 'Error al cargar', 'error');
      console.error('GET /tiempo-aprendizaje', e);
    }
  }

  async function loadRanking(){
    rankingEl.innerHTML = 'Cargando…';
    try {
      const res = await window.LC_get(window.LC_EP + '/clasificacion');
      const ranking = (res?.clasificacion || [])
        .map(c => ({ clase: c.clase, puntos: c.tiempo_aprendizaje || 0, escudo_url: c.escudo_url }))
        .sort((a, b) => b.puntos - a.puntos);
      rankingEl.innerHTML = renderRankingTable(ranking);
    } catch(e) {
      rankingEl.innerHTML = '<div class="lc-meta">Error al cargar la clasificación.</div>';
      console.error('GET /clasificacion (tiempo-aprendizaje ranking)', e);
    }
  }

  mesSel.addEventListener('change', loadRecord);
  claseSel.addEventListener('change', loadRecord);

  // Guardar
  guardarBtn.addEventListener('click', async () => {
    const { year, month } = parseMes();
    const clase  = claseSel.value;
    const puntos = parseInt(puntosInp.value || '0', 10);

    if (!clase) return setStatus(msg, 'Selecciona una clase', 'error');
    if (puntosInp.value === '' || Number.isNaN(puntos)) return setStatus(msg, 'Escribe los puntos', 'error');

    setButtonLoading(guardarBtn, true);
    try {
      const res = await window.LC_post(window.LC_EP + '/tiempo-aprendizaje', { clase, year, month, puntos });
      setButtonLoading(guardarBtn, false);
      if (res && res.ok) {
        puntosInp.value = res.puntos;
        editado = false;
        const mesTxt = mesSel.options[mesSel.selectedIndex]?.text || '';
        setStatus(msg, `Guardado ✅ ${clase} · ${mesTxt}: ${res.puntos} puntos`, 'success');
        loadRanking();
      } else {
        setStatus(msg, 'Error: ' + (res?.error || 'Error desconocido'), 'error');
      }
    } catch(e) {
      setButtonLoading(guardarBtn, false);
      setStatus(msg, 'Error al guardar: ' + (e.message || ''), 'error');
      console.error('POST /tiempo-aprendizaje', e);
    }
  });

  // Borrar mes
  borrarBtn.addEventListener('click', async () => {
    const { year, month } = parseMes();
    const clase = claseSel.value;
    if (!clase) return setStatus(msg, 'Selecciona una clase', 'error');

    setButtonLoading(borrarBtn, true);
    try {
      const res = await window.LC_post(window.LC_EP + '/tiempo-aprendizaje', { clase, year, month, borrar: true });
      setButtonLoading(borrarBtn, false);
      if (res && res.ok) {
        puntosInp.value = 0;
        setStatus(msg, 'Registro eliminado', 'info');
        loadRanking();
      } else {
        setStatus(msg, 'Error: ' + (res?.error || 'Error desconocido'), 'error');
      }
    } catch(e) {
      setButtonLoading(borrarBtn, false);
      setStatus(msg, 'Error al borrar: ' + (e.message || ''), 'error');
      console.error('POST /tiempo-aprendizaje (borrar)', e);
    }
  });

  // Carga inicial
  loadRecord();
  loadRanking();

  return {
    refresh(newCtx){ ctx = newCtx; loadRecord(); loadRanking(); }
  };
}
