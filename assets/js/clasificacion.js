// JavaScript para Clasificación
(function() {
    'use strict';
    
    let clasificacionData = null;
    let porDeporteData = [];
    let porActividadData = [];
    let alumnosData = [];
    let trimestresPoblados = false;
    let temporadasPoblados = false;

    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        initClasificacion();
    });

    function initClasificacion() {
        // Configurar tabs
        setupTabs();

        // Configurar filtro de período
        setupPeriodoBar();

        // Cargar datos
        loadClasificacion();
    }

    // Filtro de período (general/semanal/mensual/trimestral) encima de las pestañas.
    function setupPeriodoBar() {
        const periodoSel = document.getElementById('lc-periodo');
        if (!periodoSel) return;

        const semanaField = document.getElementById('lc-periodo-semana-field');
        const mesField = document.getElementById('lc-periodo-mes-field');
        const trimestreField = document.getElementById('lc-periodo-trimestre-field');
        const temporadaField = document.getElementById('lc-periodo-temporada-field');
        const semanaSel = document.getElementById('lc-periodo-semana');
        const mesSel = document.getElementById('lc-periodo-mes');
        const trimestreSel = document.getElementById('lc-periodo-trimestre');
        const temporadaSel = document.getElementById('lc-periodo-temporada');

        const hoy = new Date();
        semanaSel.innerHTML = seasonWeeksFor(hoy).map(w => `<option value="${w.value}">${w.label}</option>`).join('');
        mesSel.innerHTML = seasonMonthsFor(hoy).map(m => `<option value="${m.value}">${m.label}</option>`).join('');

        const actual = isoYearWeek(new Date(Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())));
        const actualStr = `${actual.year}-W${String(actual.week).padStart(2, '0')}`;
        if (semanaSel.querySelector(`option[value="${actualStr}"]`)) semanaSel.value = actualStr;

        const mesActual = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
        if (mesSel.querySelector(`option[value="${mesActual}"]`)) mesSel.value = mesActual;

        function updateVisibility() {
            const periodo = periodoSel.value;
            semanaField.style.display = periodo === 'semanal' ? '' : 'none';
            mesField.style.display = periodo === 'mensual' ? '' : 'none';
            trimestreField.style.display = periodo === 'trimestral' ? '' : 'none';
            temporadaField.style.display = periodo === 'temporada' ? '' : 'none';
        }
        updateVisibility();

        periodoSel.addEventListener('change', () => { updateVisibility(); loadClasificacion(); });
        semanaSel.addEventListener('change', loadClasificacion);
        mesSel.addEventListener('change', loadClasificacion);
        trimestreSel.addEventListener('change', loadClasificacion);
        temporadaSel.addEventListener('change', loadClasificacion);
    }

    // Rellena el desplegable de trimestres con los configurados en Ajustes > Liga
    // Creus (llegan en la respuesta de /clasificacion). Solo la primera vez: cambiar
    // de período no debe perder la selección ya hecha por el usuario.
    function poblarTrimestres(trimestres) {
        if (trimestresPoblados) return;
        const trimestreSel = document.getElementById('lc-periodo-trimestre');
        if (!trimestreSel) return;
        const lista = trimestres || [];
        trimestreSel.innerHTML = lista.map(t => `<option value="${lcEscapar(t.label)}">${lcEscapar(t.label)}</option>`).join('');
        // Solo se da por poblado si realmente hay opciones: si la respuesta viene
        // vacía, una carga posterior debe poder volver a intentarlo.
        trimestresPoblados = lista.length > 0;
    }

    // Rellena el desplegable de temporadas (cursos escolares) con las configuradas
    // en Ajustes > Liga Convivencia, excluyendo la temporada activa (esa ya se ve con
    // "General" — este selector es para consultar temporadas YA cerradas).
    function poblarTemporadas(temporadas) {
        if (temporadasPoblados) return;
        const temporadaSel = document.getElementById('lc-periodo-temporada');
        if (!temporadaSel) return;
        const cerradas = (temporadas || []).filter(t => t.fin);
        temporadaSel.innerHTML = cerradas.map(t => `<option value="${lcEscapar(t.label)}">${lcEscapar(t.label)}</option>`).join('');
        temporadasPoblados = cerradas.length > 0;
    }

    // Etiqueta contextual junto al filtro: qué temporada se está viendo en "General".
    // En "Mensual" indica qué semanas cuenta ese mes: cada semana va entera al
    // mes de su miércoles, así que el rango puede empezar o acabar en otro mes.
    function actualizarEtiquetaTemporada(temporadaActiva, periodo, semanasMes) {
        const label = document.getElementById('lc-temporada-actual-label');
        if (!label) return;
        const fmt = (iso) => { const [, m, d] = iso.split('-'); return `${d}/${m}`; };
        if (periodo === 'mensual' && Array.isArray(semanasMes)) {
            label.textContent = `Semanas del ${fmt(semanasMes[0])} al ${fmt(semanasMes[1])} (T. Aprendizaje: el mes completo)`;
        } else {
            label.textContent = (periodo === 'general' && temporadaActiva) ? `Temporada actual: ${temporadaActiva.label}` : '';
        }
    }

    // Construye los parámetros de consulta según el período seleccionado.
    // Sin selector de período en la página (caso defensivo), no añade nada => "general".
    function buildQueryParams() {
        const periodoSel = document.getElementById('lc-periodo');
        const periodo = periodoSel ? periodoSel.value : 'general';
        const params = new URLSearchParams();
        params.set('periodo', periodo);

        if (periodo === 'semanal') {
            const parsed = parseWeekInput(document.getElementById('lc-periodo-semana')?.value);
            if (parsed) {
                params.set('year', parsed.year);
                params.set('week', parsed.week);
            }
        } else if (periodo === 'mensual') {
            const [y, m] = (document.getElementById('lc-periodo-mes')?.value || '').split('-');
            if (y && m) {
                params.set('year', y);
                params.set('month', m);
            }
        } else if (periodo === 'trimestral') {
            const val = document.getElementById('lc-periodo-trimestre')?.value;
            if (val) params.set('trimestre', val);
        } else if (periodo === 'temporada') {
            const val = document.getElementById('lc-periodo-temporada')?.value;
            if (val) params.set('temporada', val);
        }

        return params;
    }
    
    function setupTabs() {
        const tabs = document.querySelectorAll('.lc-tab');
        const panels = document.querySelectorAll('.lc-tab-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');

                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                panels.forEach(p => { p.hidden = true; });

                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                document.getElementById(`lc-tab-${targetTab}`).hidden = false;
            });
        });
    }
    
    async function loadClasificacion() {
        try {
            const params = buildQueryParams();
            const url = `${LC_CLASIFICACION.endpoint}?${params.toString()}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': LC_CLASIFICACION.nonce
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.ok) {
                clasificacionData = data.clasificacion;
                porDeporteData = data.por_deporte || [];
                porActividadData = data.por_actividad || [];
                alumnosData = data.alumnos || [];
                poblarTrimestres(data.trimestres);
                poblarTemporadas(data.temporadas);
                actualizarEtiquetaTemporada(data.temporada_activa, data.periodo, data.semanas_mes);
                renderClasificacion();
                hideLoading();
            } else {
                throw new Error(data.error || 'Error desconocido');
            }
            
        } catch (error) {
            console.error('Error cargando clasificación:', error);
            showError();
        }
    }
    
    function renderClasificacion() {
        if (!clasificacionData) return;
        
        // Renderizar tabla general
        renderGeneralTable();

        // Renderizar tabla individual (Semáforo, Partes y Extraordinarias no tienen desglose)
        renderSemaforoTable();
        renderPartesTable();
        renderExtraordinariasTable();
        renderTiempoAprendizajeTable();
        renderAlumnosTable();

        // Deportes y Biblioteca: una sola tira de pestañas con "General" + cada
        // deporte/actividad individual (sin tabla agregada por separado).
        renderSubBreakdown(
            document.getElementById('lc-subtabs-deportes'),
            document.getElementById('lc-subpanels-deportes'),
            [rankingFor('deportes'), ...porDeporteData],
            'dep'
        );
        renderSubBreakdown(
            document.getElementById('lc-subtabs-biblioteca'),
            document.getElementById('lc-subpanels-biblioteca'),
            [rankingFor('biblioteca'), ...porActividadData],
            'bib'
        );

        // Actualizar timestamp
        updateTimestamp();
    }

    // Construye la entrada "General" (ranking agregado) para anteponerla al
    // desglose por deporte/actividad, a partir de la tabla general ya cargada.
    function rankingFor(campo) {
        const ranking = clasificacionData
            .map(c => ({ clase: c.clase, puntos: c[campo], escudo_url: c.escudo_url }))
            .sort((a, b) => b.puntos - a.puntos);
        return { value: 'general', label: 'General', ranking };
    }

    // Imagen del escudo + nombre de la clase, usado en todas las tablas de clasificación.
    function claseCell(clase, escudoUrl) {
        const img = escudoUrl ? `<img class="lc-escudo" src="${lcEscapar(escudoUrl)}" alt="" loading="lazy">` : '';
        return `<span class="lc-clase-cell">${img}<span>${lcEscapar(clase)}</span></span>`;
    }

    function renderRankingTable(ranking) {
        const rows = (ranking || []).map((r, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${claseCell(r.clase, r.escudo_url)}</td>
                <td>${r.puntos}</td>
            </tr>
        `).join('');
        return `
            <table class="lc-table">
                <thead>
                    <tr><th>Posición</th><th>Clase</th><th>Puntos</th></tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`;
    }

    // Pinta un grupo de sub-pestañas (una por deporte/actividad) dentro de un contenedor ya existente.
    function renderSubBreakdown(tabsEl, panelsEl, items, idPrefix) {
        if (!tabsEl || !panelsEl || !items.length) return;

        tabsEl.innerHTML = items.map((it, i) => `
            <button class="lc-tab${i === 0 ? ' active' : ''}" data-subtab="${idPrefix}-${it.value}" role="tab" aria-selected="${i === 0}">${lcEscapar(it.label)}</button>
        `).join('');

        panelsEl.innerHTML = items.map((it, i) => `
            <div class="lc-tab-panel" id="lc-subpanel-${idPrefix}-${it.value}" role="tabpanel" ${i === 0 ? '' : 'hidden'}>
                ${renderRankingConToggle(it.ranking, it.ranking_alumnos)}
            </div>
        `).join('');

        bindRankingToggles(panelsEl);

        const tabs = tabsEl.querySelectorAll('.lc-tab');
        const panels = panelsEl.querySelectorAll('.lc-tab-panel');
        tabs.forEach((tab) => {
            tab.addEventListener('click', function () {
                tabs.forEach((t) => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
                panels.forEach((pnl) => { pnl.hidden = true; });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                document.getElementById(`lc-subpanel-${this.dataset.subtab}`).hidden = false;
            });
        });
    }
    
    // Ranking global de alumnos: suma de lo conseguido en las pruebas
    // individuales y de sus partes de conducta.
    function renderAlumnosTable() {
        const tbody = document.getElementById('lc-alumnos-tbody');
        if (!tbody) return;

        if (!alumnosData.length) {
            tbody.innerHTML = '<tr><td colspan="4">Todavía no hay puntos registrados por alumno.</td></tr>';
            return;
        }

        tbody.innerHTML = alumnosData.map((a, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${lcEscapar(a.nombre)}</td>
                <td>${lcEscapar(a.clase)}</td>
                <td class="lc-total">${a.puntos}</td>
            </tr>
        `).join('');
    }

    function renderGeneralTable() {
        const tbody = document.getElementById('lc-general-tbody');
        if (!tbody) return;
        
        tbody.innerHTML = clasificacionData.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${claseCell(clase.clase, clase.escudo_url)}</td>
                <td>${clase.semaforo}</td>
                <td>${clase.deportes}</td>
                <td>${clase.partes}</td>
                <td>${clase.extraordinarias ?? 0}</td>
                <td>${clase.biblioteca}</td>
                <td>${clase.tiempo_aprendizaje ?? 0}</td>
                <td class="lc-total">${clase.total}</td>
            </tr>
        `).join('');
    }
    
    function renderSemaforoTable() {
        const tbody = document.getElementById('lc-semaforo-tbody');
        if (!tbody) return;
        
        // Ordenar por puntos de semáforo
        const sorted = [...clasificacionData].sort((a, b) => b.semaforo - a.semaforo);
        
        tbody.innerHTML = sorted.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${claseCell(clase.clase, clase.escudo_url)}</td>
                <td>${clase.semaforo}</td>
            </tr>
        `).join('');
    }
    
    function renderExtraordinariasTable() {
        const tbody = document.getElementById('lc-extraordinarias-tbody');
        if (!tbody) return;

        const sorted = [...clasificacionData].sort((a, b) => (b.extraordinarias ?? 0) - (a.extraordinarias ?? 0));

        tbody.innerHTML = sorted.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${claseCell(clase.clase, clase.escudo_url)}</td>
                <td class="lc-total">${clase.extraordinarias ?? 0}</td>
            </tr>
        `).join('');
    }

    function renderTiempoAprendizajeTable() {
        const tbody = document.getElementById('lc-tiempo-aprendizaje-tbody');
        if (!tbody) return;

        const sorted = [...clasificacionData].sort((a, b) => (b.tiempo_aprendizaje ?? 0) - (a.tiempo_aprendizaje ?? 0));

        tbody.innerHTML = sorted.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${claseCell(clase.clase, clase.escudo_url)}</td>
                <td class="lc-total">${clase.tiempo_aprendizaje ?? 0}</td>
            </tr>
        `).join('');
    }

    function renderPartesTable() {
        const tbody = document.getElementById('lc-partes-tbody');
        if (!tbody) return;
        
        // Ordenar por puntos de partes
        const sorted = [...clasificacionData].sort((a, b) => b.partes - a.partes);
        
        tbody.innerHTML = sorted.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${claseCell(clase.clase, clase.escudo_url)}</td>
                <td>${clase.partes_positivos}</td>
                <td>${clase.partes_leves}</td>
                <td>${clase.partes_graves}</td>
                <td class="lc-total">${clase.partes}</td>
            </tr>
        `).join('');
    }
    
    function updateTimestamp() {
        const timestamp = document.getElementById('lc-timestamp');
        if (timestamp) {
            const now = new Date();
            timestamp.textContent = now.toLocaleString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
    
    function hideLoading() {
        const loading = document.querySelector('.lc-loading');
        const content = document.getElementById('lc-clasificacion-content');
        
        if (loading) loading.style.display = 'none';
        if (content) content.style.display = 'block';
    }
    
    function showError() {
        const loading = document.querySelector('.lc-loading');
        const error = document.getElementById('lc-error');
        
        if (loading) loading.style.display = 'none';
        if (error) error.style.display = 'block';
    }
    
    // Auto-refresh cada 5 minutos
    setInterval(function() {
        if (clasificacionData) {
            loadClasificacion();
        }
    }, 300000); // 5 minutos
    
})();
