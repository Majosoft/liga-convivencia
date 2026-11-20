// UI Partes
// Cada parte es un registro propio asociado a un alumno (o a toda la clase si
// no se elige ninguno), en vez de los tres
// contadores por clase que había antes. Mismo contrato que ui-semaforo.js:
// uiPartes() pinta los campos y bindPartes(container, ctx) recibe
// { clase, year, week, onDirtyChange } y devuelve { refresh(ctx) }.

const PT_TIPOS = [
  { value: 'positivo', label: 'Positivo', icono: '✅', clave: 'positivo' },
  { value: 'leve',     label: 'Leve',     icono: '⚠️', clave: 'leve' },
  { value: 'grave',    label: 'Grave',    icono: '❌', clave: 'grave' },
];

function uiPartes(){
  const pts = lcPuntosPartes();
  const tipoOptions = PT_TIPOS
    .map(t => `<option value="${t.value}">${t.icono} ${t.label} (${lcTextoPuntos(pts[t.clave]).replace(' cada uno', '')})</option>`)
    .join('');

  return `
    <div>
      <h4 class="lc-module-title">Partes – registro semanal</h4>

      <div class="lc-meta" id="pt-compweek-label"></div>

      <div class="lc-module-split">
      <div class="lc-module-form">

        <div class="lc-form-grid">
          <div class="lc-field">
            <label for="pt-alumno">Alumno de <span id="pt-clase-label"></span> (opcional)</label>
            ${alumnoPickerHtml('pt-alumno')}
          </div>
          <div class="lc-field">
            <label for="pt-tipo">Tipo de parte</label>
            <select id="pt-tipo">${tipoOptions}</select>
          </div>
        </div>

        <div class="lc-field">
          <label for="pt-notas">Notas</label>
          <textarea id="pt-notas" placeholder="Motivo del parte"></textarea>
        </div>

        <div class="lc-actions">
          <button class="lc-btn lc-btn-primary" id="pt-save" type="button">Registrar parte</button>
          <span id="pt-msg" class="lc-status" aria-live="polite"></span>
        </div>

      </div>

      <div class="lc-module-side">
        <div class="lc-panel-section">
          <h5 class="lc-section-title">Partes de la semana</h5>
          <div id="pt-resumen" class="lc-meta"></div>
          <div id="pt-list" class="lc-meta">Sin partes esta semana.</div>
        </div>

        <div class="lc-panel-section">
          <h5 class="lc-section-title">Clasificación – Partes</h5>
          <div id="pt-ranking" class="lc-meta">Cargando…</div>
        </div>
      </div>

      </div>
    </div>`;
}

function bindPartes(container, ctx){
  function markDirty(){ ctx.onDirtyChange?.(true); }

  const tipo       = container.querySelector('#pt-tipo');
  const notas      = container.querySelector('#pt-notas');
  const btn        = container.querySelector('#pt-save');
  const msg        = container.querySelector('#pt-msg');
  const label      = container.querySelector('#pt-compweek-label');
  const claseLabel = container.querySelector('#pt-clase-label');
  const list       = container.querySelector('#pt-list');
  const resumen    = container.querySelector('#pt-resumen');
  const rankingEl  = container.querySelector('#pt-ranking');
  const picker     = bindAlumnoPicker(container.querySelector('#pt-alumno').closest('[data-alumno-picker]'), {
    onChange: markDirty,
    opcional: 'Toda la clase',
  });

  tipo.addEventListener('change', markDirty);
  notas.addEventListener('input', markDirty);

  function updateLabel(){
    claseLabel.textContent = ctx.clase || '';
    if(!ctx.year || !ctx.week){ label.textContent = ''; return; }
    const monday = dateFromWeekInput(`${ctx.year}-W${String(ctx.week).padStart(2,'0')}`);
    if(!monday){ label.textContent = ''; return; }
    const cw = compWeekOf(monday);
    const season = `${cw.seasonYear}-${String(cw.seasonYear+1).slice(-2)}`;
    label.textContent = `Semana de competición: ${cw.week} · Temporada ${season}`;
  }

  function renderResumen(data){
    const pts = lcPuntosPartes();
    const partes = PT_TIPOS
      .map(t => `${t.icono} ${data.resumen?.[t.value] || 0} ${t.label.toLowerCase()}`)
      .join(' · ');
    resumen.textContent = `${partes} — Total: ${data.puntos || 0} puntos`;
  }

  function renderList(partes){
    if (!partes || !partes.length){
      list.innerHTML = '<div class="lc-meta">Sin partes esta semana.</div>';
      return;
    }
    const etiqueta = Object.fromEntries(PT_TIPOS.map(t => [t.value, `${t.icono} ${t.label}`]));
    const rows = partes.map(p => `
      <tr>
        <td>${lcEscapar(p.alumno)}</td>
        <td>${etiqueta[p.tipo] || p.tipo}</td>
        <td>${p.puntos}</td>
        <td>${lcEscapar(p.notas)}</td>
        <td><button type="button" class="lc-btn lc-btn-secondary lc-btn-xs lc-parte-borrar" data-id="${p.id}">Borrar</button></td>
      </tr>`).join('');

    list.innerHTML = `
      <div class="lc-table-container">
        <table class="lc-table lc-partes-table">
          <thead><tr><th>Alumno</th><th>Tipo</th><th>Puntos</th><th>Notas</th><th></th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;

    list.querySelectorAll('.lc-parte-borrar').forEach(b => {
      b.addEventListener('click', () => borrar(parseInt(b.dataset.id, 10)));
    });
  }

  async function loadData(){
    if(!ctx.clase || !ctx.year || !ctx.week){ return; }
    await picker.setClase(ctx.clase);
    try{
      const q = new URLSearchParams({ clase: ctx.clase, year: ctx.year, week: ctx.week });
      const res = await window.LC_get(window.LC_EP + '/partes?' + q.toString());
      if(res && res.ok){
        renderList(res.partes);
        renderResumen(res);
      }
      ctx.onDirtyChange?.(false);
    } catch(e){
      setStatus(msg, 'Error al cargar', 'error');
      console.error('GET /partes', e);
    }
    loadRanking();
  }

  async function loadRanking(){
    rankingEl.innerHTML = 'Cargando…';
    try{
      const res = await window.LC_get(window.LC_EP + '/clasificacion');
      const clases = (res?.clasificacion || [])
        .map(c => ({ clase: c.clase, puntos: c.partes || 0, escudo_url: c.escudo_url }))
        .sort((a, b) => b.puntos - a.puntos);
      rankingEl.innerHTML = renderRankingConToggle(clases, res?.alumnos_partes);
      bindRankingToggles(rankingEl);
    } catch(e){
      rankingEl.innerHTML = '<div class="lc-meta">Error al cargar la clasificación.</div>';
      console.error('GET /clasificacion (partes)', e);
    }
  }

  async function borrar(id){
    if (!id || !window.confirm('¿Borrar este parte?')) return;
    try{
      const r = await fetch(window.LC_EP + '/partes', {
        method: 'DELETE',
        headers: {'Content-Type':'application/json','X-WP-Nonce': window.LC_NONCE},
        body: JSON.stringify({ id }),
      });
      const res = await r.json();
      if (res && res.ok){
        renderList(res.partes);
        renderResumen(res);
        setStatus(msg, 'Parte borrado', 'info');
        loadRanking();
      } else {
        setStatus(msg, res?.error || 'No se pudo borrar', 'error');
      }
    } catch(e){
      setStatus(msg, 'Error al borrar', 'error');
      console.error('DELETE /partes', e);
    }
  }

  btn.addEventListener('click', async ()=>{
    if(!ctx.clase || !ctx.year || !ctx.week) return;

    setStatus(msg, '', 'info');
    setButtonLoading(btn, true);

    const payload = {
      clase: ctx.clase, year: ctx.year, week: ctx.week,
      alumno_id: picker.value(),
      tipo: tipo.value,
      notas: (notas.value||''),
    };
    try{
      const res = await window.LC_post(window.LC_EP + '/partes', payload);
      setButtonLoading(btn, false);
      if(res && res.ok){
        renderList(res.partes);
        renderResumen(res);
        notas.value = '';
        setStatus(msg, 'Parte registrado ✅', 'success');
        ctx.onDirtyChange?.(false);
        loadRanking();
      } else {
        setStatus(msg, res?.error || 'Error al guardar', 'error');
      }
    }catch(e){
      setButtonLoading(btn, false);
      setStatus(msg, 'Error al guardar: ' + (e.message||''), 'error');
      console.error('POST /partes', payload, e);
    }
  });

  updateLabel();
  loadData();

  return {
    refresh(newCtx){ ctx = newCtx; updateLabel(); loadData(); }
  };
}
