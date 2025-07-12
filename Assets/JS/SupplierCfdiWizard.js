import {CfdiWizard} from './CfdiWizard.js';
async function buscarProductos(query) {
    const formData = new FormData();
    formData.append('action', 'search-own-product');
    formData.append('query', query);

    try {
        const response = await fetch('CfdiSupplierWizard', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        actualizarTablaProductos(data);
    } catch {
        actualizarTablaProductos([]);
    }
}

function actualizarTablaProductos(productos) {
    const tbody = document.querySelector('#tablaProductos tbody');
    tbody.innerHTML = '';

    if (!productos.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No se encontraron productos</td></tr>';
        return;
    }

    productos.forEach(p => {
        const row = `<tr>
            <td>${p.referencia}</td>
            <td>${p.descripcion}</td>
            <td>
                <button type="button" class="btn btn-sm btn-success seleccionar-producto"
                    data-referencia="${p.referencia}">
                    Seleccionar
                </button>
            </td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', row);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const wizard = new CfdiWizard();
    wizard.init('Importar');

    const tablaProductos = document.getElementById('tablaProductos');

/*    document.getElementById('readCfdiBtn').addEventListener('click', function () {
        $('#cfdiInputModal').modal('show');
    });*/

    document.querySelectorAll('.custom-file-input').forEach(input => {
        input.addEventListener('change', function () {
            const fileName = this.value.split('\\').pop();
            const label = this.nextElementSibling;

            if (!label.dataset.defaultTitle) {
                label.dataset.defaultTitle = label.innerHTML;
            }

            if (fileName === '') {
                label.classList.remove("selected");
                label.innerHTML = label.dataset.defaultTitle;
            } else {
                label.classList.add("selected");
                label.innerHTML = fileName;
            }
        });
    });

    let conceptoActualIndex = null;

    // Abrir modal para vincular
    document.querySelectorAll('.vincular-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            conceptoActualIndex = this.dataset.index;
            $('#vincularModal').modal('show');
        });
    });

    // Desvincular
    document.querySelectorAll('.desvincular-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = this.dataset.index;

            // Limpia input hidden
            const inputHidden = document.getElementById(`concepto-referencia-${index}`);
            if (inputHidden) {
                inputHidden.value = '';
            }

            // Limpia celda visible
            const celdaRef = document.getElementById(`referencia-${index}`);
            if (celdaRef) {
                celdaRef.textContent = '—';
            }
        });
    });

    // Buscar productos
    document.getElementById('buscarProductoInput').addEventListener('input', function () {
        const query = this.value.trim();
        if (query.length >= 2) {
            buscarProductos(query);
        }
    });

    // Seleccionar producto
    document.getElementById('tablaProductos').addEventListener('click', function (e) {
        if (e.target.classList.contains('seleccionar-producto')) {
            const referencia = e.target.dataset.referencia;

            // Actualiza input y celda
            const inputHidden = document.getElementById(`concepto-referencia-${conceptoActualIndex}`);
            if (inputHidden) {
                inputHidden.value = referencia;
            }
            const celdaRef = document.getElementById(`referencia-${conceptoActualIndex}`);
            if (celdaRef) {
                celdaRef.textContent = referencia;
            }

            $('#vincularModal').modal('hide');
        }
    });
});
