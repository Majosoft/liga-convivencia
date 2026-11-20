// Helpers para manejo de semanas y fechas

// Escapa texto para meterlo en HTML (contenido o atributo). Todo lo que venga
// del servidor o lo escriba alguien (nombres, notas, emails...) pasa por aquí
// antes de ir a innerHTML: el servidor quita etiquetas, pero no comillas.
function lcEscapar(s){
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
}


// Clases/grupos de la competición. Configurables desde Ajustes > Liga Convivencia > Clases;
// llega vía window.LC_SETTINGS.clases (no se puede leer como const a nivel de módulo
// porque ese script se inyecta justo antes de app.js, después de que este archivo
// ya se haya evaluado — por eso se resuelve de forma diferida en cada llamada).
function lcClases(){
  return (window.LC_SETTINGS && window.LC_SETTINGS.clases) || ["1ºA","1ºB","2ºA","2ºB","3ºA","3ºB","4ºA","4ºB"];
}

// Puntuación configurable de Semáforo y Partes (Competición > Semáforo/Partes).
// Los defaults replican la puntuación cableada de siempre, por si el panel se
// carga sin ajustes (o con una versión anterior del plugin).
function lcPuntosSemaforo(){
  return Object.assign({ verde: 1, amarillo: -1, rojo: -3 },
    (window.LC_SETTINGS && window.LC_SETTINGS.semaforo) || {});
}

function lcPuntosPartes(){
  return Object.assign({ positivo: 3, leve: -1, grave: -10 },
    (window.LC_SETTINGS && window.LC_SETTINGS.partes) || {});
}

// "+1 punto cada uno" / "−3 puntos cada uno". El signo menos es el tipográfico
// (U+2212), que es el que ya usaban estos textos.
function lcTextoPuntos(v){
  const n = Number(v) || 0;
  if (n === 0) return 'No cuenta';
  const abs = Math.abs(n);
  return `${n > 0 ? '+' : '−'}${abs} ${abs === 1 ? 'punto' : 'puntos'} cada uno`;
}

// Año y semana ISO-8601 de una fecha. Tiene que dar EXACTAMENTE lo mismo que
// date('o')/date('W') de PHP: el panel guarda los registros con este número y el
// backend filtra por trimestre con el suyo, así que cualquier discrepancia hace
// que lo registrado desaparezca al filtrar.
//
// La clave es que la semana 1 es la que contiene el primer jueves del año. El 4
// de enero siempre cae en ella, pero NO siempre es jueves (en 2026 es domingo),
// así que hay que retroceder desde él hasta su jueves antes de contar semanas.
function isoYearWeek(date){
  const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
  // Jueves de la semana de la fecha: define a qué año ISO pertenece.
  d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 6) % 7) + 3);
  // Jueves de la semana 1 de ese año ISO.
  const firstThu = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
  firstThu.setUTCDate(firstThu.getUTCDate() - ((firstThu.getUTCDay() + 6) % 7) + 3);
  const week = 1 + Math.round((d - firstThu) / (7 * 86400000));
  return { year: d.getUTCFullYear(), week };
}

function currentISOWeek(){
  const hoy = new Date();
  return isoYearWeek(new Date(Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())));
}

// === Utilidades de temporada (Octubre -> Junio) ===
// Temporada configurada en Ajustes > Liga Convivencia > Temporadas, si la hay.
// Es la que manda sobre la regla octubre->junio de abajo, que solo actúa como
// respaldo cuando no hay ninguna temporada definida.
function lcParseISODate(s){
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
  return m ? new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])) : null;
}

function lcTemporadaConfig(){
  const t = window.LC_SETTINGS && window.LC_SETTINGS.temporada;
  const inicio = t ? lcParseISODate(t.inicio) : null;
  if (!inicio) return null;
  let fin = lcParseISODate(t.fin);
  if (!fin){
    // Temporada en curso (sin fecha de fin): se ofrece un curso completo.
    fin = new Date(inicio);
    fin.setUTCFullYear(fin.getUTCFullYear() + 1);
    fin.setUTCDate(fin.getUTCDate() - 7);
  }
  return { inicio, fin };
}

// Trimestres configurados, ya resueltos a rango de semanas ISO. Se comparan por
// (año, semana) igual que hace el backend en rest-clasificacion.php, para que la
// etiqueta del selector coincida exactamente con lo que luego filtra la
// Clasificación y no prometa un trimestre que después no incluya esa semana.
function lcOrdenSemana(p){ return p.year * 100 + p.week; }

function lcTrimestresRango(){
  const list = (window.LC_SETTINGS && window.LC_SETTINGS.trimestres) || [];
  return list.map(t => {
    const ini = lcParseISODate(t.inicio), fin = lcParseISODate(t.fin);
    if (!ini || !fin) return null;
    return { label: t.label || '', desde: lcOrdenSemana(isoYearWeek(ini)), hasta: lcOrdenSemana(isoYearWeek(fin)) };
  }).filter(Boolean);
}

function compSeasonYear(d){ // año de temporada (empieza en OCT)
  const y = d.getUTCFullYear(), m = d.getUTCMonth(); // 0..11
  return (m >= 9) ? y : (y - 1); // oct..dic => y; ene..jun => y-1
}

function mondayOfISOWeekUTC(d){
  const dt = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
  const day = (dt.getUTCDay() || 7); // 1..7 (Mon=1)
  if (day !== 1) dt.setUTCDate(dt.getUTCDate() - (day - 1));
  return dt;
}

// Inicio de temporada: lunes de la semana que CONTIENE el 1 de octubre
function seasonStartMonday(seasonY){
  const oct1 = new Date(Date.UTC(seasonY, 9, 1)); // 1 de octubre (UTC)
  return mondayOfISOWeekUTC(oct1); // puede caer en septiembre (p.ej. 29/09/2025)
}

function seasonEndMonday(seasonY){ // último lunes de JUN (inclusive)
  const jun30 = new Date(Date.UTC(seasonY + 1, 5, 30));
  return mondayOfISOWeekUTC(jun30);
}

// Genera opciones "Semana 1..N (dd/mm–dd/mm)" + value ISO "YYYY-Www"
function seasonWeeksFor(date){
  const cfg = lcTemporadaConfig();
  let start, end;
  if (cfg){
    start = mondayOfISOWeekUTC(cfg.inicio);
    end   = mondayOfISOWeekUTC(cfg.fin);
  } else {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const seasonY = compSeasonYear(d);
    start = seasonStartMonday(seasonY);
    end   = seasonEndMonday(seasonY);
  }
  const trimestres = lcTrimestresRango();
  const out = []; let idx = 1;
  for (let cur = new Date(start); cur <= end; cur.setUTCDate(cur.getUTCDate() + 7)) {
    const iso = isoYearWeek(cur);
    const isoStr = `${iso.year}-W${String(iso.week).padStart(2,'0')}`;
    const from = new Date(cur), to = new Date(cur); to.setUTCDate(to.getUTCDate() + 6);
    const fmt = (x)=>`${String(x.getUTCDate()).padStart(2,'0')}/${String(x.getUTCMonth()+1).padStart(2,'0')}`;
    const n = lcOrdenSemana(iso);
    const tri = trimestres.find(t => n >= t.desde && n <= t.hasta);
    out.push({ value: isoStr, label: `Semana ${idx} (${fmt(from)}–${fmt(to)})`, trimestre: tri ? tri.label : '' });
    idx++;
  }
  return out;
}

// Genera opciones "Mes YYYY" + value "YYYY-MM" para los 9 meses de la temporada (Oct..Jun)
function seasonMonthsFor(date){
  const nombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  const cfg = lcTemporadaConfig();
  let cur, last;
  if (cfg){
    cur  = new Date(Date.UTC(cfg.inicio.getUTCFullYear(), cfg.inicio.getUTCMonth(), 1));
    last = new Date(Date.UTC(cfg.fin.getUTCFullYear(), cfg.fin.getUTCMonth(), 1));
  } else {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const seasonY = compSeasonYear(d);
    cur  = new Date(Date.UTC(seasonY, 9, 1));       // octubre
    last = new Date(Date.UTC(seasonY + 1, 5, 1));   // junio
  }
  const out = [];
  while (cur <= last){
    const y = cur.getUTCFullYear(), m = cur.getUTCMonth();
    out.push({ value: `${y}-${String(m + 1).padStart(2,'0')}`, label: `${nombres[m]} ${y}` });
    cur.setUTCMonth(cur.getUTCMonth() + 1);
  }
  return out;
}

// Parsear "YYYY-Www"
function parseWeekInput(val){
  const m = /^(\d{4})-W(\d{2})$/.exec(val||'');
  if(!m) return null; return { year: +m[1], week: +m[2] };
}

// Convertir "YYYY-Www" a lunes ISO (UTC)
function dateFromWeekInput(val){
  const m = /^(\d{4})-W(\d{2})$/.exec(val||'');
  if(!m) return null;
  const year = +m[1], week = +m[2];
  const simple = new Date(Date.UTC(year, 0, 4));  // 4 de enero en semana 1
  const dayOfWeek = (simple.getUTCDay() || 7);
  const mondayWeek1 = new Date(simple);
  mondayWeek1.setUTCDate(simple.getUTCDate() - (dayOfWeek - 1));
  const monday = new Date(mondayWeek1);
  monday.setUTCDate(mondayWeek1.getUTCDate() + (week - 1) * 7);
  return monday;
}

// Semana de competición para una fecha dada
function compWeekOf(dateLike){
  const d = (dateLike instanceof Date) ? dateLike : new Date(dateLike);
  const seasonYear = compSeasonYear(d);
  const start = seasonStartMonday(seasonYear);  // lunes de la semana que contiene 1/oct
  const monday = mondayOfISOWeekUTC(d);
  const diffDays = Math.floor((monday - start) / 86400000);
  const w = Math.floor(diffDays / 7) + 1;
  return { seasonYear, week: Math.max(1, w) };
}

// === Componentes/UI compartidos ===

// Lista de participantes dinámica (usada por Deportes-Pin Pon y Biblioteca).
// Sustituye el código que antes estaba duplicado en ambos módulos.
function createParticipantList({ container, classes, initial = [], onChange, extraField = null }){
  let participantes = initial.slice();

  function optionsHtml(selected){
    return classes.map(c => `<option value="${lcEscapar(c)}" ${c === selected ? 'selected' : ''}>${lcEscapar(c)}</option>`).join('');
  }

  function render(){
    if (participantes.length === 0){
      container.innerHTML = '<div class="lc-participant-empty">No hay participantes añadidos</div>';
      return;
    }

    container.innerHTML = participantes.map((p, i) => `
      <div class="lc-participant-row" data-index="${i}">
        <select class="lc-participant-clase" aria-label="Clase del participante">${optionsHtml(p.clase)}</select>
        <input type="text" class="lc-participant-nombre" placeholder="Nombre del participante"
               value="${lcEscapar(p.nombre)}" aria-label="Nombre del participante"/>
        ${extraField ? `<input type="number" step="any" class="lc-participant-extra" placeholder="${extraField.label}"
               value="${lcEscapar(p[extraField.name])}" aria-label="${extraField.label}"/>` : ''}
        <button type="button" class="lc-btn lc-btn-icon lc-btn-secondary lc-participant-remove" aria-label="Quitar participante">×</button>
      </div>
    `).join('');

    container.querySelectorAll('.lc-participant-row').forEach(row => {
      const idx = +row.dataset.index;
      row.querySelector('.lc-participant-clase').addEventListener('change', (e) => {
        participantes[idx].clase = e.target.value;
        onChange?.(participantes);
      });
      row.querySelector('.lc-participant-nombre').addEventListener('input', (e) => {
        participantes[idx].nombre = e.target.value;
        onChange?.(participantes);
      });
      const extraInput = row.querySelector('.lc-participant-extra');
      if (extraInput){
        extraInput.addEventListener('input', (e) => {
          participantes[idx][extraField.name] = e.target.value;
          onChange?.(participantes);
        });
      }
      row.querySelector('.lc-participant-remove').addEventListener('click', () => {
        participantes.splice(idx, 1);
        render();
        onChange?.(participantes);
      });
    });
  }

  render();

  return {
    add(){
      participantes.push({ clase: classes[0], nombre: '' });
      render();
      onChange?.(participantes);
    },
    getValues(){
      return participantes.filter(p => p.nombre && p.nombre.trim());
    },
    reset(newInitial = []){
      participantes = newInitial.slice();
      render();
    },
  };
}

// ===== Selector de alumno =====
// Componente compartido por Deportes, Biblioteca y Partes: un <select> con los
// alumnos de una clase y un alta al vuelo sin salir del formulario. El alumno
// creado queda disponible para el resto de módulos, que es justo lo que se pide.

// Marcado del componente. `id` sirve para asociar la etiqueta del campo.
function alumnoPickerHtml(id){
  return `
    <div class="lc-alumno-picker" data-alumno-picker>
      <div class="lc-alumno-elegir">
        <select id="${id}" class="lc-alumno-select"></select>
        <button type="button" class="lc-btn lc-btn-secondary lc-btn-sm lc-alumno-nuevo">+ Nuevo</button>
      </div>
      <div class="lc-alumno-alta" hidden>
        <input type="text" class="lc-alumno-nombre" placeholder="Nombre y apellidos" autocomplete="off"/>
        <button type="button" class="lc-btn lc-btn-primary lc-btn-sm lc-alumno-guardar">Añadir</button>
        <button type="button" class="lc-btn lc-btn-secondary lc-btn-sm lc-alumno-cancelar">Cancelar</button>
      </div>
      <span class="lc-alumno-msg lc-status" aria-live="polite"></span>
    </div>`;
}

// Enlaza el componente. Devuelve { setClase, value, setValue, reload }.
// Con `opcional` (texto de la opción), el alumno no es obligatorio: se añade
// una primera opción con valor vacío, para la que value() devuelve 0.
function bindAlumnoPicker(root, { onChange, opcional } = {}){
  const select   = root.querySelector('.lc-alumno-select');
  const btnNuevo = root.querySelector('.lc-alumno-nuevo');
  const alta     = root.querySelector('.lc-alumno-alta');
  const elegir   = root.querySelector('.lc-alumno-elegir');
  const input    = root.querySelector('.lc-alumno-nombre');
  const btnOk    = root.querySelector('.lc-alumno-guardar');
  const btnNo    = root.querySelector('.lc-alumno-cancelar');
  const msg      = root.querySelector('.lc-alumno-msg');

  let clase = '';

  function modoAlta(activo){
    alta.hidden = !activo;
    elegir.hidden = activo;
    if (activo){ input.value = ''; input.focus(); }
  }

  async function cargar(seleccionado){
    if (!clase){ select.innerHTML = '<option value="">—</option>'; return; }
    try {
      const res = await window.LC_get(window.LC_EP + '/alumnos?' + new URLSearchParams({ clase }));
      const alumnos = (res && res.ok && res.alumnos) ? res.alumnos : [];
      const opciones = alumnos.map(a => `<option value="${lcEscapar(a.id)}">${lcEscapar(a.nombre)}</option>`).join('');
      if (opcional){
        select.innerHTML = `<option value="">${opcional}</option>` + opciones;
      } else {
        select.innerHTML = alumnos.length
          ? opciones
          : `<option value="">— Ningún alumno en ${lcEscapar(clase)} —</option>`;
      }
      if (seleccionado && alumnos.some(a => String(a.id) === String(seleccionado))){
        select.value = String(seleccionado);
      }
    } catch(e){
      select.innerHTML = '<option value="">Error al cargar</option>';
      console.error('GET /alumnos', e);
    }
  }

  async function crear(){
    const nombre = (input.value || '').trim();
    if (!nombre){ setStatus(msg, 'Escribe el nombre', 'error'); return; }
    if (!clase){ setStatus(msg, 'Selecciona antes la clase', 'error'); return; }
    setButtonLoading(btnOk, true);
    try {
      const res = await window.LC_post(window.LC_EP + '/alumnos', { nombre, clase });
      setButtonLoading(btnOk, false);
      if (!res || !res.ok){ setStatus(msg, res?.error || 'No se pudo crear', 'error'); return; }
      await cargar(res.alumno.id);
      modoAlta(false);
      setStatus(msg, '', 'info');
      onChange?.();
    } catch(e){
      setButtonLoading(btnOk, false);
      setStatus(msg, 'Error de conexión', 'error');
      console.error('POST /alumnos', e);
    }
  }

  btnNuevo.addEventListener('click', () => modoAlta(true));
  btnNo.addEventListener('click', () => { modoAlta(false); setStatus(msg, '', 'info'); });
  btnOk.addEventListener('click', crear);
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter'){ e.preventDefault(); crear(); } });
  select.addEventListener('change', () => onChange?.());

  return {
    async setClase(nueva, seleccionado){
      clase = nueva || '';
      modoAlta(false);
      await cargar(seleccionado);
    },
    value(){ return parseInt(select.value, 10) || 0; },
    async setValue(id){ await cargar(id); },
  };
}

// Engancha los botones +/- de cualquier ".lc-stepper" dentro de un contenedor.
// Genérico: no conoce los ids de los inputs, solo opera sobre el <input> que
// encuentre dentro de su propio ".lc-stepper", y dispara un evento 'input' real
// para que los listeners ya existentes (markDirty, recálculo de totales...) se
// enteren igual que si el usuario hubiera escrito el valor a mano.
function bindSteppers(container){
  container.querySelectorAll('.lc-stepper').forEach(stepper => {
    const input = stepper.querySelector('input');
    if (!input) return;
    stepper.querySelectorAll('.lc-stepper-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const step = parseInt(btn.dataset.step, 10) || 0;
        const min = input.min !== '' ? parseInt(input.min, 10) : -Infinity;
        const max = input.max !== '' ? parseInt(input.max, 10) : Infinity;
        const next = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 0) + step));
        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  });
}

// Estado de carga de un botón mientras hay una petición en curso (evita doble envío).
function setButtonLoading(btn, isLoading){
  if (!btn) return;
  if (isLoading){
    if (btn.dataset.lcOriginalHtml === undefined) btn.dataset.lcOriginalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="lc-spinner" aria-hidden="true"></span> Guardando…';
    btn.disabled = true;
    btn.classList.add('is-loading');
  } else {
    if (btn.dataset.lcOriginalHtml !== undefined) btn.innerHTML = btn.dataset.lcOriginalHtml;
    btn.disabled = false;
    btn.classList.remove('is-loading');
  }
}

// Mensaje de estado consistente (éxito/error/info) con soporte aria-live.
function setStatus(el, message, type = 'info'){
  if (!el) return;
  el.textContent = message || '';
  el.classList.remove('success', 'error', 'info');
  if (message) el.classList.add(type);
}

// Tabla de ranking de alumnos. Incluye su clase, porque el mismo nombre puede
// repetirse entre grupos y el alumno puntúa siempre para el suyo.
function renderRankingAlumnos(ranking){
  if (!ranking || !ranking.length){
    return '<div class="lc-meta">Sin datos todavía.</div>';
  }
  const rows = ranking.map((r, i) =>
    `<tr><td>${i + 1}</td><td>${lcEscapar(r.nombre)}</td><td>${lcEscapar(r.clase)}</td><td>${r.puntos}</td></tr>`
  ).join('');
  return `
    <div class="lc-table-container">
      <table class="lc-table">
        <thead><tr><th>Posición</th><th>Alumno</th><th>Clase</th><th>Puntos</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

// Bloque de clasificación con conmutador Clases / Alumnos.
// El conmutador SOLO se pinta si hay ranking de alumnos: en una prueba que no es
// individual no habría nada al otro lado, y un botón que no lleva a ningún sitio
// confunde más que ayuda.
function renderRankingConToggle(rankingClases, rankingAlumnos){
  const hayAlumnos = !!(rankingAlumnos && rankingAlumnos.length);
  const toggle = hayAlumnos ? `
      <div class="lc-ranking-toggle" role="group" aria-label="Ver clasificación por">
        <button type="button" class="lc-toggle-btn is-active" data-vista="clases" aria-pressed="true">Clases</button>
        <button type="button" class="lc-toggle-btn" data-vista="alumnos" aria-pressed="false">Alumnos</button>
      </div>` : '';

  return `
    <div class="lc-ranking-switch" data-ranking-switch>
      ${toggle}
      <div data-vista-panel="clases">${renderRankingTable(rankingClases)}</div>
      ${hayAlumnos ? `<div data-vista-panel="alumnos" hidden>${renderRankingAlumnos(rankingAlumnos)}</div>` : ''}
    </div>`;
}

// Engancha todos los conmutadores que haya dentro de un contenedor. Se llama
// después de volcar el HTML, porque los botones se crean en ese momento.
function bindRankingToggles(root){
  if (!root) return;
  root.querySelectorAll('[data-ranking-switch]').forEach(bloque => {
    const botones = bloque.querySelectorAll('.lc-toggle-btn');
    const paneles = bloque.querySelectorAll('[data-vista-panel]');
    botones.forEach(btn => {
      btn.addEventListener('click', () => {
        botones.forEach(b => { b.classList.remove('is-active'); b.setAttribute('aria-pressed', 'false'); });
        btn.classList.add('is-active');
        btn.setAttribute('aria-pressed', 'true');
        paneles.forEach(p => { p.hidden = p.dataset.vistaPanel !== btn.dataset.vista; });
      });
    });
  });
}

// Tabla de ranking (Posición/Clase/Puntos) — usada por el mini-ranking de
// Deportes/Biblioteca en el panel, a partir de los datos de GET /clasificacion.
function renderRankingTable(ranking){
  if (!ranking || !ranking.length){
    return '<div class="lc-meta">Sin datos todavía.</div>';
  }
  const rows = ranking.map((r, i) => {
    const img = r.escudo_url ? `<img class="lc-escudo" src="${lcEscapar(r.escudo_url)}" alt="" loading="lazy">` : '';
    return `<tr><td>${i + 1}</td><td><span class="lc-clase-cell">${img}<span>${lcEscapar(r.clase)}</span></span></td><td>${r.puntos}</td></tr>`;
  }).join('');
  return `
    <div class="lc-table-container">
      <table class="lc-table">
        <thead><tr><th>Posición</th><th>Clase</th><th>Puntos</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}
