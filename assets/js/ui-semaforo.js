// UI Semáforo
// Contrato: uiSemaforo() devuelve solo el marcado del módulo (clase/semana las
// gestiona la barra de contexto compartida en app.js). bindSemaforo(container, ctx)
// recibe { clase, year, week, onDirtyChange } y devuelve { refresh(ctx) }.

function uiSemaforo(){
  const pts = lcPuntosSemaforo();
  return `
    <div>
      <h4 class="lc-module-title">Semáforo – registro semanal</h4>

      <div class="lc-module-form">

      <div class="lc-counters">
        <div class="lc-counter-row is-success">
          <div class="lc-counter-info">
            <span class="lc-counter-dot" aria-hidden="true">🟢</span>
            <div class="lc-counter-text">
              <label class="lc-counter-name" for="sf-verdes">Gomets Verdes</label>
              <span class="lc-counter-hint">${lcTextoPuntos(pts.verde)}</span>
            </div>
          </div>
          <div class="lc-stepper">
            <button type="button" class="lc-stepper-btn" data-step="-1" aria-label="Restar gomet verde">−</button>
            <input type="number" id="sf-verdes" value="0" min="0" inputmode="numeric"/>
            <button type="button" class="lc-stepper-btn" data-step="1" aria-label="Sumar gomet verde">+</button>
          </div>
        </div>
        <div class="lc-counter-row is-warning">
          <div class="lc-counter-info">
            <span class="lc-counter-dot" aria-hidden="true">🟡</span>
            <div class="lc-counter-text">
              <label class="lc-counter-name" for="sf-amarillos">Gomets Amarillos</label>
              <span class="lc-counter-hint">${lcTextoPuntos(pts.amarillo)}</span>
            </div>
          </div>
          <div class="lc-stepper">
            <button type="button" class="lc-stepper-btn" data-step="-1" aria-label="Restar gomet amarillo">−</button>
            <input type="number" id="sf-amarillos" value="0" min="0" inputmode="numeric"/>
            <button type="button" class="lc-stepper-btn" data-step="1" aria-label="Sumar gomet amarillo">+</button>
          </div>
        </div>
        <div class="lc-counter-row is-danger">
          <div class="lc-counter-info">
            <span class="lc-counter-dot" aria-hidden="true">🔴</span>
            <div class="lc-counter-text">
              <label class="lc-counter-name" for="sf-rojos">Gomets Rojos</label>
              <span class="lc-counter-hint">${lcTextoPuntos(pts.rojo)}</span>
            </div>
          </div>
          <div class="lc-stepper">
            <button type="button" class="lc-stepper-btn" data-step="-1" aria-label="Restar gomet rojo">−</button>
            <input type="number" id="sf-rojos" value="0" min="0" inputmode="numeric"/>
            <button type="button" class="lc-stepper-btn" data-step="1" aria-label="Sumar gomet rojo">+</button>
          </div>
        </div>
      </div>

      <div class="lc-form-grid">
        <div class="lc-field">
          <label for="sf-notas">Notas</label>
          <textarea id="sf-notas" placeholder="Observaciones"></textarea>
        </div>
        <div class="lc-field">
          <label for="sf-total">Total actual</label>
          <div class="lc-total-display">
            <input type="number" id="sf-total" value="0" readonly tabindex="-1"/>
          </div>
        </div>
      </div>

      <div class="lc-actions">
        <button class="lc-btn lc-btn-primary" id="sf-save" type="button">Guardar</button>
        <span id="sf-msg" class="lc-status" aria-live="polite"></span>
      </div>

      </div>
    </div>`;
}

function bindSemaforo(container, ctx){
  const verdes    = container.querySelector('#sf-verdes');
  const amarillos = container.querySelector('#sf-amarillos');
  const rojos     = container.querySelector('#sf-rojos');
  const total     = container.querySelector('#sf-total');
  const notas     = container.querySelector('#sf-notas');
  const btnSave   = container.querySelector('#sf-save');
  const msg       = container.querySelector('#sf-msg');

  function updateTotal(){
    const ver = parseInt(verdes.value || 0, 10);
    const ama = parseInt(amarillos.value || 0, 10);
    const roj = parseInt(rojos.value || 0, 10);
    // Misma fórmula que calculateTotal() en rest-semaforo.php: los puntos ya
    // llevan su signo, así que es una suma.
    const pts = lcPuntosSemaforo();
    total.value = (ver * pts.verde) + (ama * pts.amarillo) + (roj * pts.rojo);
  }

  function markDirty(){ ctx.onDirtyChange?.(true); }

  [verdes, amarillos, rojos].forEach(inp => inp.addEventListener('input', ()=>{ updateTotal(); markDirty(); }));
  notas.addEventListener('input', markDirty);
  bindSteppers(container);

  async function loadData(){
    setStatus(msg, '', 'info');
    if(!ctx.clase || !ctx.year || !ctx.week){ return; }
    try{
      const q = new URLSearchParams({ clase: ctx.clase, year: ctx.year, week: ctx.week });
      const res = await window.LC_get(window.LC_EP + '/semaforo?' + q.toString());
      if(res.data){
        verdes.value = res.data.gomets_verdes ?? 0;
        amarillos.value = res.data.gomets_amarillos ?? 0;
        rojos.value = res.data.gomets_rojos ?? 0;
        notas.value = res.data.notas ?? '';
        updateTotal();
        setStatus(msg, 'Datos cargados', 'info');
      } else {
        verdes.value = 0;
        amarillos.value = 0;
        rojos.value = 0;
        notas.value = '';
        updateTotal();
        setStatus(msg, 'Nueva semana: introduce los gomets de esta semana', 'info');
      }
      ctx.onDirtyChange?.(false);
    }catch(e){
      setStatus(msg, 'Error al cargar', 'error');
      console.error('GET /semaforo', e);
    }
  }

  btnSave.addEventListener('click', async ()=>{
    if(!ctx.clase || !ctx.year || !ctx.week) return;
    setStatus(msg, '', 'info');
    setButtonLoading(btnSave, true);

    const payload = {
      clase: ctx.clase, year: ctx.year, week: ctx.week,
      gomets_verdes: parseInt(verdes.value||0, 10),
      gomets_amarillos: parseInt(amarillos.value||0, 10),
      gomets_rojos: parseInt(rojos.value||0, 10),
      notas: (notas.value||'')
    };

    try{
      const res = await window.LC_post(window.LC_EP + '/semaforo', payload);
      setButtonLoading(btnSave, false);
      if (res && res.ok) {
        total.value = res.current_total ?? 0; // total acumulado real tras guardar
        setStatus(msg, 'Guardado ✅', 'success');
        ctx.onDirtyChange?.(false);
      } else {
        setStatus(msg, 'Error: ' + (res?.error || 'Respuesta inválida'), 'error');
        console.error('POST /semaforo response:', res);
      }
    }catch(e){
      setButtonLoading(btnSave, false);
      setStatus(msg, 'Error al guardar: ' + (e.message || 'Error de conexión'), 'error');
      console.error('POST /semaforo', payload, e);
    }
  });

  loadData();

  return {
    refresh(newCtx){ ctx = newCtx; loadData(); }
  };
}
