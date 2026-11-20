// UI Gomets (1.0): el profesor elige un día y ve SOLO sus clases de ese día,
// según su horario, para poner un gomet por hora. Se guarda al pulsar (sin
// botón "Guardar"); pulsar otra vez el color marcado lo quita. El recuento
// semanal del Semáforo lo rehace el servidor (LC_Horario::recalcular_semana).
// Mismo contrato que el resto de módulos: uiGomets() / bindGomets(container, ctx).

const LC_GOMETS_COLORES = [
  { color: 'verde',    emoji: '🟢', texto: 'Verde',    clase: 'is-success' },
  { color: 'amarillo', emoji: '🟡', texto: 'Amarillo', clase: 'is-warning' },
  { color: 'rojo',     emoji: '🔴', texto: 'Rojo',     clase: 'is-danger'  },
];
const LC_DIAS_SEMANA = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

function lcFechaISO(d){
  const z = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
}

// Día lectivo más cercano hacia atrás (sábado/domingo -> viernes).
function lcUltimoLectivo(d){
  const r = new Date(d);
  while (r.getDay() === 0 || r.getDay() === 6) r.setDate(r.getDate() - 1);
  return r;
}

// Suma/resta días lectivos saltando fines de semana.
function lcMoverLectivo(d, paso){
  const r = new Date(d);
  do { r.setDate(r.getDate() + paso); } while (r.getDay() === 0 || r.getDay() === 6);
  return r;
}

function uiGomets(){
  return `
    <div>
      <h4 class="lc-module-title">Semáforo – mis clases</h4>
      <details class="lc-mi-horario" open>
        <summary>Mi horario de la semana</summary>
        <div id="gm-horario"><p class="lc-meta">Cargando…</p></div>
        <p class="lc-meta">Comprueba que está bien: es el horario con el que se reparten tus gomets.
          Si algo no cuadra, avisa a quien administra la Liga.</p>
      </details>
      <div class="lc-gomets-dia">
        <button type="button" class="lc-btn lc-btn-secondary lc-btn-sm" id="gm-prev" aria-label="Día anterior">‹</button>
        <input type="date" id="gm-fecha" aria-label="Día"/>
        <button type="button" class="lc-btn lc-btn-secondary lc-btn-sm" id="gm-next" aria-label="Día siguiente">›</button>
        <button type="button" class="lc-btn lc-btn-secondary lc-btn-sm" id="gm-hoy">Hoy</button>
      </div>
      <p class="lc-meta" id="gm-titulo" aria-live="polite"></p>
      <div id="gm-lista" class="lc-gomets-lista"></div>
      <p class="lc-meta">Un gomet por hora. Se guarda al pulsar; pulsa otra vez el color marcado para quitarlo.</p>
    </div>`;
}

// Cuadrícula lunes-viernes x 6 horas con el horario completo del profesor,
// apoyos incluidos (marcados "sin gomet"), para que compruebe que es el suyo.
function lcHorarioSemanaHtml(sesiones){
  if (!sesiones.length){
    return '<p class="lc-meta">No tienes ninguna clase de ESO en el horario cargado.</p>';
  }
  const DIAS = ['L', 'M', 'X', 'J', 'V'];
  const NOMBRES = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
  const hoy = new Date().getDay(); // 1 = lunes ... 5 = viernes
  const celda = {};
  sesiones.forEach(s => { (celda[`${s.dia}-${s.hora}`] ||= []).push(s); });
  const cab = DIAS.map((d, i) =>
    `<th scope="col" class="${i + 1 === hoy ? 'is-hoy' : ''}"><abbr title="${NOMBRES[i]}">${d}</abbr></th>`).join('');
  let filas = '';
  for (let h = 1; h <= 6; h++){
    filas += `<tr><th scope="row">${h}ª</th>`;
    for (let d = 1; d <= 5; d++){
      const lista = celda[`${d}-${h}`] || [];
      const contenido = lista.map(s => {
        const mezcla = s.gomet && s.clases.length > 1 ? `<span class="lc-hz-nota">cuenta para ${s.clases.map(lcEscapar).join(' y ')}</span>` : '';
        const apoyo = s.gomet ? '' : '<span class="lc-hz-nota">sin gomet</span>';
        return `<div class="lc-hz-sesion${s.gomet ? '' : ' is-apoyo'}"><strong>${lcEscapar(s.materia)}</strong> ${lcEscapar(s.grupo)}${mezcla}${apoyo}</div>`;
      }).join('');
      filas += `<td class="${d === hoy ? 'is-hoy' : ''}">${contenido}</td>`;
    }
    filas += '</tr>';
  }
  return `<div class="lc-hz-scroll"><table class="lc-hz"><thead><tr><th></th>${cab}</tr></thead><tbody>${filas}</tbody></table></div>`;
}

function bindGomets(container){
  const EP = window.LC_EP;
  const input = container.querySelector('#gm-fecha');
  const titulo = container.querySelector('#gm-titulo');
  const lista = container.querySelector('#gm-lista');
  const hoy = lcUltimoLectivo(new Date());
  input.max = lcFechaISO(new Date());
  let fecha = hoy;
  let peticion = 0;

  function filaHtml(s){
    const otras = s.clases.length > 1
      ? ` <span class="lc-meta">(cuenta para ${s.clases.map(lcEscapar).join(' y ')})</span>` : '';
    const botones = LC_GOMETS_COLORES.map(c => `
      <button type="button" class="lc-gomet-btn ${c.clase}${s.color === c.color ? ' is-activo' : ''}"
        data-color="${c.color}" aria-pressed="${s.color === c.color}" aria-label="${c.texto}, ${s.hora}ª hora">
        <span aria-hidden="true">${c.emoji}</span> ${c.texto}
      </button>`).join('');
    return `
      <div class="lc-gomet-fila" data-sesion="${s.id}">
        <div class="lc-gomet-info">
          <strong>${s.hora}ª hora</strong> · ${lcEscapar(s.materia)} · ${lcEscapar(s.grupo)}${otras}
        </div>
        <div class="lc-gomet-botones">${botones}</div>
        <div class="lc-status" aria-live="polite"></div>
      </div>`;
  }

  async function cargar(){
    const iso = lcFechaISO(fecha);
    input.value = iso;
    container.querySelector('#gm-next').disabled = lcMoverLectivo(fecha, 1) > new Date();
    titulo.textContent = `${LC_DIAS_SEMANA[fecha.getDay()]} ${fecha.toLocaleDateString('es-ES', { day: 'numeric', month: 'long' })}`;
    lista.innerHTML = '<p class="lc-meta">Cargando…</p>';
    const mia = ++peticion;
    const res = await window.LC_get(EP + '/gomets?' + new URLSearchParams({ fecha: iso }));
    if (mia !== peticion) return; // otro día pedido mientras tanto
    if (!res || !res.ok){
      lista.innerHTML = '<p class="lc-alert lc-alert-danger">No se han podido cargar tus clases.</p>';
      return;
    }
    if (res.aviso){
      lista.innerHTML = `<p class="lc-meta">${lcEscapar(res.aviso)}</p>`;
    } else if (!res.sesiones.length){
      lista.innerHTML = '<p class="lc-meta">Este día no tienes clases con gomet.</p>';
    } else {
      lista.innerHTML = res.sesiones.map(filaHtml).join('');
    }
  }

  // Un solo listener delegado en la lista (se rehace al cambiar de día).
  lista.addEventListener('click', async (e)=>{
    const btn = e.target.closest('.lc-gomet-btn');
    if (!btn) return;
    const fila = btn.closest('.lc-gomet-fila');
    const botones = fila.querySelectorAll('.lc-gomet-btn');
    if ([...botones].some(b => b.disabled)) return; // ya hay un guardado en curso
    const quitar = btn.classList.contains('is-activo');
    const color = quitar ? '' : btn.dataset.color;
    const status = fila.querySelector('.lc-status');
    botones.forEach(b => { b.disabled = true; });
    setStatus(status, 'Guardando…', 'info');
    try {
      await window.LC_post(EP + '/gomets', { sesion_id: Number(fila.dataset.sesion), fecha: lcFechaISO(fecha), color });
      botones.forEach(b => {
        const activo = b.dataset.color === color;
        b.classList.toggle('is-activo', activo);
        b.setAttribute('aria-pressed', String(activo));
      });
      setStatus(status, quitar ? 'Gomet quitado.' : 'Guardado.', 'success');
    } catch (err) {
      setStatus(status, err.message || 'No se ha podido guardar.', 'error');
    } finally {
      botones.forEach(b => { b.disabled = false; });
    }
  });

  container.querySelector('#gm-prev').addEventListener('click', ()=>{ fecha = lcMoverLectivo(fecha, -1); cargar(); });
  container.querySelector('#gm-next').addEventListener('click', ()=>{ fecha = lcMoverLectivo(fecha, 1); cargar(); });
  container.querySelector('#gm-hoy').addEventListener('click', ()=>{ fecha = hoy; cargar(); });
  input.addEventListener('change', ()=>{
    if (!input.value) return;
    const [y, m, d] = input.value.split('-').map(Number);
    fecha = new Date(y, m - 1, d);
    cargar();
  });

  // El horario no cambia al moverse de día: se pide una vez.
  window.LC_get(EP + '/gomets/horario').then(res => {
    const caja = container.querySelector('#gm-horario');
    caja.innerHTML = res && res.ok
      ? lcHorarioSemanaHtml(res.sesiones)
      : '<p class="lc-alert lc-alert-danger">No se ha podido cargar tu horario.</p>';
  });

  cargar();
  return { refresh(){ cargar(); } };
}
