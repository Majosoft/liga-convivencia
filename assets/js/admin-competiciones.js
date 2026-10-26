// Filas dinámicas para las tablas de Deportes/Actividades en Ajustes > Liga Convivencia
(function () {
  function nextIndex(tbody) {
    return tbody.querySelectorAll('tr.lc-admin-row').length;
  }

  // Selector de escudo (media uploader de WordPress) dentro de una fila de
  // Clases. No-op si la fila no tiene widget de escudo (resto de tablas).
  function bindEscudo(row) {
    const cell = row.querySelector('.lc-escudo-cell');
    if (!cell) return;
    const selectBtn = cell.querySelector('.lc-escudo-select');
    const editBtn = cell.querySelector('.lc-escudo-edit');
    const removeBtn = cell.querySelector('.lc-escudo-remove');
    const input = cell.querySelector('.lc-escudo-input');
    const preview = cell.querySelector('.lc-escudo-preview');
    const nombreInput = row.querySelector('input[type="text"]');

    // Un único estado en la celda: el CSS se encarga de mostrar el botón de subir
    // o el par Editar/Quitar según haya escudo o no.
    function setEscudo(id, url) {
      input.value = id;
      if (url) {
        preview.src = url;
      } else {
        preview.removeAttribute('src');
      }
      cell.classList.toggle('has-escudo', !!id);
      cell.classList.toggle('is-empty', !id);
    }

    // Misma ventana del gestor de medios para "Seleccionar imagen" y para "Editar":
    // editar es volver a elegir, con la imagen actual ya marcada en la biblioteca.
    function abrirSelector() {
      const nombre = nombreInput ? nombreInput.value : '';
      const frame = wp.media({
        title: 'Seleccionar escudo' + (nombre ? ' para ' + nombre : ''),
        button: { text: 'Usar esta imagen' },
        multiple: false,
        library: { type: 'image' },
      });
      frame.on('open', () => {
        const actual = parseInt(input.value, 10);
        if (!actual) return;
        const attachment = wp.media.attachment(actual);
        attachment.fetch();
        frame.state().get('selection').add([attachment]);
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        const url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
        setEscudo(attachment.id, url);
      });
      frame.open();
    }

    selectBtn.addEventListener('click', abrirSelector);
    editBtn.addEventListener('click', abrirSelector);
    removeBtn.addEventListener('click', () => setEscudo(0, ''));
  }

  function bindRow(row) {
    const modoSelect = row.querySelector('.lc-admin-modo');
    if (modoSelect) {
      modoSelect.addEventListener('change', () => {
        row.dataset.modo = modoSelect.value;
      });
    }
    bindEscudo(row);
    const removeBtn = row.querySelector('.lc-admin-remove-row');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => row.remove());
    }
  }

  function setupGroup(tableId, addBtnId, templateId) {
    const table = document.getElementById(tableId);
    const addBtn = document.getElementById(addBtnId);
    const template = document.getElementById(templateId);
    if (!table || !addBtn || !template) return;

    const tbody = table.querySelector('tbody');
    tbody.querySelectorAll('tr.lc-admin-row').forEach(bindRow);

    addBtn.addEventListener('click', () => {
      const idx = nextIndex(tbody);
      const html = template.innerHTML.replace(/__INDEX__/g, idx);
      const tmp = document.createElement('tbody');
      tmp.innerHTML = html;
      const row = tmp.querySelector('tr.lc-admin-row');
      tbody.appendChild(row);
      bindRow(row);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    setupGroup('lc-admin-rows-deportes', 'lc-admin-add-deporte', 'lc-admin-template-deporte');
    setupGroup('lc-admin-rows-actividades', 'lc-admin-add-actividad', 'lc-admin-template-actividad');
    setupGroup('lc-admin-rows-clases', 'lc-admin-add-clase', 'lc-admin-template-clase');
    setupGroup('lc-admin-rows-trimestres', 'lc-admin-add-trimestre', 'lc-admin-template-trimestre');
    setupGroup('lc-admin-rows-temporadas', 'lc-admin-add-temporada', 'lc-admin-template-temporada');
    setupGroup('lc-wizard-rows-trimestres', 'lc-wizard-add-trimestre', 'lc-wizard-template-trimestre');
    // Icono de la App profesorado: mismo selector que los escudos.
    document.querySelectorAll('.lc-app-icono-row').forEach(bindEscudo);
  });
})();
