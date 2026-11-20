// Pruebas de assets/js/helpers.js con el corredor de pruebas de Node (sin dependencias).
//
//   node --test tests/js/
//
// helpers.js es un script de navegador (funciones globales que leen
// window.LC_SETTINGS), así que se carga en un contexto aislado de `vm` con un
// `window` simulado.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const RAIZ = new URL('../../', import.meta.url);
const CODIGO = readFileSync(new URL('assets/js/helpers.js', RAIZ), 'utf8');

function cargar(LC_SETTINGS = {}) {
  const ctx = vm.createContext({ window: { LC_SETTINGS }, console });
  vm.runInContext(CODIGO, ctx);
  return ctx;
}
const h = cargar();

// --------------------------------------------------------------- semanas ISO
test('isoYearWeek da la misma semana que PHP (date("o-W")) cada día de 2024 a 2028', () => {
  // Si el navegador y el servidor no coinciden, lo registrado en una semana
  // "desaparece" al filtrar por esa semana en la clasificación.
  const php = JSON.parse(execFileSync('php', ['-r', `
    date_default_timezone_set('UTC');
    $out = [];
    for ($t = strtotime('2024-01-01'); $t <= strtotime('2028-12-31'); $t += 86400) $out[date('Y-m-d', $t)] = date('o-W', $t);
    echo json_encode($out);`]).toString());
  let dias = 0;
  for (const [fecha, esperado] of Object.entries(php)) {
    const [y, m, d] = fecha.split('-').map(Number);
    const r = h.isoYearWeek(new Date(Date.UTC(y, m - 1, d)));
    assert.equal(`${r.year}-${String(r.week).padStart(2, '0')}`, esperado, `fecha ${fecha}`);
    dias++;
  }
  assert.equal(dias, 1827);
});

test('isoYearWeek: casos límite de cambio de año', () => {
  const sem = (y, m, d) => { const r = h.isoYearWeek(new Date(Date.UTC(y, m - 1, d))); return [r.year, r.week]; };
  assert.deepEqual(sem(2026, 1, 1), [2026, 1]);   // jueves: semana 1 de su año
  assert.deepEqual(sem(2027, 1, 1), [2026, 53]);  // viernes: semana 53 del año anterior
  assert.deepEqual(sem(2024, 12, 30), [2025, 1]); // lunes: ya es semana 1 del año siguiente
  assert.deepEqual(sem(2026, 1, 4), [2026, 1]);   // el 4 de enero siempre está en la semana 1
});

test('dateFromWeekInput devuelve el lunes de esa semana, para todas las semanas de 2024 a 2028', () => {
  for (let y = 2024; y <= 2028; y++) {
    for (let w = 1; w <= 52; w++) {
      const val = `${y}-W${String(w).padStart(2, '0')}`;
      const lunes = h.dateFromWeekInput(val);
      assert.equal(lunes.getUTCDay(), 1, `${val} no empieza en lunes`);
      const r = h.isoYearWeek(lunes);
      assert.deepEqual([r.year, r.week], [y, w], val);
    }
  }
  assert.equal(h.dateFromWeekInput('2026-47'), null);
  assert.deepEqual(JSON.parse(JSON.stringify(h.parseWeekInput('2026-W47'))), { year: 2026, week: 47 });
});

// --------------------------------------------------------------- temporada
test('seasonWeeksFor: semanas consecutivas de la temporada configurada, con su trimestre', () => {
  const ctx = cargar({
    temporada: { inicio: '2026-09-14', fin: '2027-06-18' },
    trimestres: [{ label: '1er trimestre', inicio: '2026-09-14', fin: '2026-12-18' }],
  });
  const semanas = ctx.seasonWeeksFor(new Date(Date.UTC(2026, 10, 20)));
  assert.equal(semanas[0].value, '2026-W38');
  assert.equal(semanas[0].label, 'Semana 1 (14/09–20/09)');
  assert.equal(semanas.at(-1).value, '2027-W24');
  for (let i = 1; i < semanas.length; i++) {
    const a = ctx.dateFromWeekInput(semanas[i - 1].value), b = ctx.dateFromWeekInput(semanas[i].value);
    assert.equal((b - a) / 86400000, 7, `hueco entre ${semanas[i - 1].value} y ${semanas[i].value}`);
  }
  assert.equal(semanas.find(s => s.value === '2026-W51').trimestre, '1er trimestre');
  assert.equal(semanas.find(s => s.value === '2027-W02').trimestre, '');
});

test('seasonMonthsFor: de septiembre a junio de la temporada', () => {
  const ctx = cargar({ temporada: { inicio: '2026-09-14', fin: '2027-06-18' } });
  const meses = ctx.seasonMonthsFor(new Date());
  assert.equal(meses.length, 10);
  assert.equal(meses[0].value, '2026-09');
  assert.equal(meses[0].label, 'Septiembre 2026');
  assert.equal(meses.at(-1).value, '2027-06');
});

test('sin temporada configurada, la temporada por defecto va de octubre a junio', () => {
  const meses = h.seasonMonthsFor(new Date(Date.UTC(2026, 10, 20)));
  assert.equal(meses[0].value, '2026-10');
  assert.equal(meses.at(-1).value, '2027-06');
});

// --------------------------------------------------------------- puntos y posiciones
test('lcPuntos redondea a entero y tolera valores vacíos', () => {
  assert.equal(h.lcPuntos(2.5), 3);
  assert.equal(h.lcPuntos(13.08), 13);
  assert.equal(h.lcPuntos('7'), 7);
  assert.equal(h.lcPuntos(undefined), 0);
  assert.equal(h.lcPuntos(null), 0);
  assert.equal(h.lcPuntos('abc'), 0);
});

test('lcPosiciones: los empates comparten puesto (1, 2, 2, 4)', () => {
  const lista = [{ p: 10 }, { p: 8 }, { p: 8 }, { p: 5 }];
  assert.deepEqual([...h.lcPosiciones(lista, x => x.p)], [1, 2, 2, 4]);
  assert.deepEqual([...h.lcPosiciones([{ p: 7.4 }, { p: 7.0 }], x => x.p)], [1, 1], 'iguales una vez redondeados');
  assert.deepEqual([...h.lcPosiciones([], x => x.p)], []);
  assert.deepEqual([...h.lcPosiciones([{ p: 3 }, { p: 3 }, { p: 3 }], x => x.p)], [1, 1, 1]);
});

test('lcFilaPodio: medalla solo del 1 al 3', () => {
  assert.equal(h.lcFilaPodio(1), ' class="lc-pos-1"');
  assert.equal(h.lcFilaPodio(3), ' class="lc-pos-3"');
  assert.equal(h.lcFilaPodio(4), '');
});

test('lcTextoPuntos: texto de la puntuación con signo', () => {
  assert.equal(h.lcTextoPuntos(1), '+1 punto cada uno');
  assert.equal(h.lcTextoPuntos(-3), '−3 puntos cada uno');
  assert.equal(h.lcTextoPuntos(0), 'No cuenta');
});

// --------------------------------------------------------------- seguridad
test('lcEscapar neutraliza HTML en contenido y en atributos', () => {
  assert.equal(h.lcEscapar('<img src=x onerror=alert(1)>'), '&lt;img src=x onerror=alert(1)&gt;');
  assert.equal(h.lcEscapar('x" onmouseover="alert(1)'), 'x&quot; onmouseover=&quot;alert(1)');
  assert.equal(h.lcEscapar("O'Brien & Co"), 'O&#39;Brien &amp; Co');
  assert.equal(h.lcEscapar(null), '');
  assert.equal(h.lcEscapar(undefined), '');
  assert.equal(h.lcEscapar(42), '42');
});

test('renderRankingTable escapa los nombres y comparte puesto en los empates', () => {
  const html = h.renderRankingTable([
    { clase: '<script>x</script>', puntos: 9 },
    { clase: '1ºB', puntos: 9 },
    { clase: '1ºC', puntos: 4.6 },
  ]);
  assert.ok(!html.includes('<script>'), 'no debe colar etiquetas');
  assert.ok(html.includes('&lt;script&gt;'));
  assert.equal((html.match(/<tr class="lc-pos-1"><td>1<\/td>/g) || []).length, 2, 'dos primeros empatados');
  assert.ok(html.includes('<tr class="lc-pos-3"><td>3</td>'), 'el siguiente es el 3.º, no el 2.º');
  assert.ok(html.includes('<td>5</td></tr>'), '4,6 se muestra como 5');
  assert.equal(h.renderRankingTable([]), '<div class="lc-meta">Sin datos todavía.</div>');
});

test('renderRankingAlumnos escapa nombre y clase', () => {
  const html = h.renderRankingAlumnos([{ nombre: '"><b>', clase: '1ºA', puntos: 3 }]);
  assert.ok(!html.includes('"><b>'));
  assert.ok(html.includes('&quot;&gt;&lt;b&gt;'));
});

// --------------------------------------------------------------- configuración
test('lcClases y puntuaciones: valores por defecto y los de LC_SETTINGS', () => {
  assert.equal(h.lcClases().length, 8);
  const ctx = cargar({ clases: ['A', 'B'], semaforo: { verde: 2 } });
  assert.deepEqual([...ctx.lcClases()], ['A', 'B']);
  assert.deepEqual({ ...ctx.lcPuntosSemaforo() }, { verde: 2, amarillo: -1, rojo: -3 }, 'completa lo que falte con los valores por defecto');
  assert.deepEqual({ ...h.lcPuntosPartes() }, { positivo: 3, leve: -1, grave: -10 });
});
