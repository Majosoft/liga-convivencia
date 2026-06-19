// Helpers para manejo de semanas y fechas

function currentISOWeek(){
  const d = new Date();
  const target = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  const dayNr = (target.getUTCDay() + 6) % 7; // Mon=0
  target.setUTCDate(target.getUTCDate() - dayNr + 3);
  const firstThursday = new Date(Date.UTC(target.getUTCFullYear(),0,4));
  const diff = (target - firstThursday) / 86400000;
  const week = 1 + Math.floor(diff / 7);
  const year = target.getUTCFullYear();
  return {year, week};
}

// === Utilidades de temporada (Octubre -> Junio) ===
function isoYearWeek(date){ // {year, week} ISO del lunes de esa fecha
  const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
  const dayNr = (d.getUTCDay() + 6) % 7; // Mon=0..Sun=6
  d.setUTCDate(d.getUTCDate() - dayNr + 3);
  const firstThu = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
  const dayDiff = (d - firstThu) / 86400000;
  const week = 1 + Math.floor(dayDiff / 7);
  const year = d.getUTCFullFullYear?.() ?? d.getUTCFullYear(); // guard por compat
  return {year, week};
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
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const seasonY = compSeasonYear(d);
  const start = seasonStartMonday(seasonY);
  const end   = seasonEndMonday(seasonY);
  const out = []; let idx = 1;
  for (let cur = new Date(start); cur <= end; cur.setUTCDate(cur.getUTCDate() + 7)) {
    const iso = isoYearWeek(cur);
    const isoStr = `${iso.year}-W${String(iso.week).padStart(2,'0')}`;
    const from = new Date(cur), to = new Date(cur); to.setUTCDate(to.getUTCDate() + 6);
    const fmt = (x)=>`${String(x.getUTCDate()).padStart(2,'0')}/${String(x.getUTCMonth()+1).padStart(2,'0')}`;
    out.push({ value: isoStr, label: `Semana ${idx} (${fmt(from)}–${fmt(to)})` });
    idx++;
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
