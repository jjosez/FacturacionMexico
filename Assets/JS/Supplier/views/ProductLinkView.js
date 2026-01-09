/**
 * ProductLinkView
 * Vista para la tabla de conceptos y vinculación de productos
 */
export class ProductLinkView {
    constructor() {
        // No necesita configuración inicial, trabaja con IDs dinámicos
    }

    /**
     * Actualiza la celda de referencia visible
     * @param {string|number} conceptoIndex - Índice del concepto
     * @param {string} referencia - Referencia del producto
     */
    updateReferenceCell(conceptoIndex, referencia) {
        const cell = document.getElementById(`referencia-${conceptoIndex}`);
        if (cell) {
            cell.textContent = referencia;
            cell.classList.add('text-success', 'fw-bold');
        }
    }

    /**
     * Limpia la celda de referencia visible
     * @param {string|number} conceptoIndex - Índice del concepto
     */
    clearReferenceCell(conceptoIndex) {
        const cell = document.getElementById(`referencia-${conceptoIndex}`);
        if (cell) {
            cell.textContent = '—';
            cell.classList.remove('text-success', 'fw-bold');
        }
    }

    /**
     * Actualiza el input hidden con la referencia
     * @param {string|number} conceptoIndex - Índice del concepto
     * @param {string} referencia - Referencia del producto
     */
    updateHiddenInput(conceptoIndex, referencia) {
        const input = document.getElementById(`concepto-referencia-${conceptoIndex}`);
        if (input) {
            input.value = referencia;
        }
    }

    /**
     * Limpia el input hidden
     * @param {string|number} conceptoIndex - Índice del concepto
     */
    clearHiddenInput(conceptoIndex) {
        const input = document.getElementById(`concepto-referencia-${conceptoIndex}`);
        if (input) {
            input.value = '';
        }
    }

    /**
     * Actualiza visual feedback de éxito
     * @param {string|number} conceptoIndex - Índice del concepto
     */
    showSuccessFeedback(conceptoIndex) {
        const row = this.getConceptoRow(conceptoIndex);
        if (row) {
            row.classList.add('table-success');
            setTimeout(() => {
                row.classList.remove('table-success');
            }, 1500);
        }
    }

    /**
     * Actualiza visual feedback de error
     * @param {string|number} conceptoIndex - Índice del concepto
     */
    showErrorFeedback(conceptoIndex) {
        const row = this.getConceptoRow(conceptoIndex);
        if (row) {
            row.classList.add('table-danger');
            setTimeout(() => {
                row.classList.remove('table-danger');
            }, 1500);
        }
    }

    /**
     * Obtiene la fila del concepto
     * @param {string|number} conceptoIndex - Índice del concepto
     * @returns {HTMLElement|null}
     */
    getConceptoRow(conceptoIndex) {
        return document.querySelector(`tr[data-index="${conceptoIndex}"]`);
    }

    /**
     * Actualiza el estado de los botones según si hay vinculación
     * @param {string|number} conceptoIndex - Índice del concepto
     * @param {boolean} hasLink - Si tiene producto vinculado
     */
    updateButtonsState(conceptoIndex, hasLink) {
        const row = this.getConceptoRow(conceptoIndex);
        if (!row) return;

        const vincularBtn = row.querySelector('[data-action="product:link:open"]');
        const desvincularBtn = row.querySelector('[data-action="product:unlink"]');

        if (vincularBtn && desvincularBtn) {
            if (hasLink) {
                vincularBtn.classList.remove('btn-info');
                vincularBtn.classList.add('btn-outline-info');
                vincularBtn.textContent = 'Cambiar';
                desvincularBtn.classList.remove('d-none');
            } else {
                vincularBtn.classList.remove('btn-outline-info');
                vincularBtn.classList.add('btn-info');
                vincularBtn.textContent = 'Vincular';
                desvincularBtn.classList.add('d-none');
            }
        }
    }

    /**
     * Muestra mensaje de toast
     * @param {string} message - Mensaje a mostrar
     * @param {string} type - Tipo (success, warning, danger, info)
     */
    showToast(message, type = 'info') {
        // Si existe un sistema de toast global, usarlo
        const toastContainer = document.querySelector('.toast-container');
        if (toastContainer) {
            const toast = this.createToast(message, type);
            toastContainer.appendChild(toast);

            if (typeof bootstrap !== 'undefined') {
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();

                // Remover después de ocultar
                toast.addEventListener('hidden.bs.toast', () => {
                    toast.remove();
                });
            }
        } else {
            // Fallback a console
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    /**
     * Crea un elemento toast
     * @param {string} message - Mensaje
     * @param {string} type - Tipo
     * @returns {HTMLElement}
     */
    createToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        const typeClass = type === 'success' ? 'bg-success' :
                         type === 'danger' ? 'bg-danger' :
                         type === 'warning' ? 'bg-warning' :
                         'bg-info';

        toast.innerHTML = `
            <div class="toast-header ${typeClass} text-white">
                <strong class="me-auto">Notificación</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;

        return toast;
    }
}
