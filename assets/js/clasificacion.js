// JavaScript para Clasificación
(function() {
    'use strict';
    
    let clasificacionData = null;
    
    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        initClasificacion();
    });
    
    function initClasificacion() {
        // Configurar tabs
        setupTabs();
        
        // Cargar datos
        loadClasificacion();
    }
    
    function setupTabs() {
        const tabs = document.querySelectorAll('.lc-tab');
        const contents = document.querySelectorAll('.lc-tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                
                // Remover active de todos
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Activar tab y contenido seleccionado
                this.classList.add('active');
                document.getElementById(`lc-tab-${targetTab}`).classList.add('active');
            });
        });
    }
    
    async function loadClasificacion() {
        try {
            const response = await fetch(LC_CLASIFICACION.endpoint, {
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
        
        // Renderizar tablas individuales
        renderSemaforoTable();
        renderDeportesTable();
        renderPartesTable();
        renderBibliotecaTable();
        
        // Actualizar timestamp
        updateTimestamp();
    }
    
    function renderGeneralTable() {
        const tbody = document.getElementById('lc-general-tbody');
        if (!tbody) return;
        
        tbody.innerHTML = clasificacionData.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${clase.clase}</td>
                <td>${clase.semaforo}</td>
                <td>${clase.deportes}</td>
                <td>${clase.partes}</td>
                <td>${clase.biblioteca}</td>
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
                <td>${clase.clase}</td>
                <td>${clase.semaforo}</td>
            </tr>
        `).join('');
    }
    
    function renderDeportesTable() {
        const tbody = document.getElementById('lc-deportes-tbody');
        if (!tbody) return;
        
        // Ordenar por puntos de deportes
        const sorted = [...clasificacionData].sort((a, b) => b.deportes - a.deportes);
        
        tbody.innerHTML = sorted.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${clase.clase}</td>
                <td>${clase.deportes}</td>
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
                <td>${clase.clase}</td>
                <td>${clase.partes}</td>
            </tr>
        `).join('');
    }
    
    function renderBibliotecaTable() {
        const tbody = document.getElementById('lc-biblioteca-tbody');
        if (!tbody) return;
        
        // Ordenar por puntos de biblioteca
        const sorted = [...clasificacionData].sort((a, b) => b.biblioteca - a.biblioteca);
        
        tbody.innerHTML = sorted.map((clase, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${clase.clase}</td>
                <td>${clase.biblioteca}</td>
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
