import templateManager from './TemplateManager.js';

/**
 * WizardView — Vista del wizard.
 * Gestiona la renderización de pasos, botones y feedback visual.
 */
class WizardView {
    constructor({
        tabsSelector = '#wizardTabs .nav-link',
        panesSelector = '.tab-pane',
        prevBtnId = 'wizard:prev:btn',
        nextBtnId = 'wizard:next:btn',
        submitBtnId = 'wizard:submit:btn'
    } = {}) {
        this.tabs = document.querySelectorAll(tabsSelector);
        this.panes = document.querySelectorAll(panesSelector);
        this.prevBtn = document.getElementById(prevBtnId);
        this.nextBtn = document.getElementById(nextBtnId);
        this.submitBtn = document.getElementById(submitBtnId);
    }

    /**
     * Muestra un paso específico
     * @param {number} step - Número del paso (1-indexed)
     */
    showStep(step) {
        if (step < 1 || step > this.tabs.length) return;

        const tab = this.tabs[step - 1];
        if (tab && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }
    }

    /**
     * Actualiza el estado de los botones según el paso actual
     * @param {number} currentStep - Paso actual
     * @param {number} totalSteps - Total de pasos
     */
    updateButtons(currentStep, totalSteps) {
        // Botón anterior
        if (this.prevBtn) {
            this.prevBtn.disabled = (currentStep === 1);
        }

        // Botón siguiente
        if (this.nextBtn) {
            this.nextBtn.classList.toggle('d-none', currentStep === totalSteps);
        }

        // Botón enviar/finalizar
        if (this.submitBtn) {
            this.submitBtn.classList.toggle('d-none', currentStep !== totalSteps);
        }
    }

    /**
     * Actualiza el label del botón de envío
     * @param {string} label - Texto del botón
     */
    updateSubmitButtonLabel(label) {
        if (this.submitBtn) {
            this.submitBtn.textContent = label;
        }
    }

    /**
     * Deshabilita todos los botones de navegación
     */
    disableNavigation() {
        if (this.prevBtn) this.prevBtn.disabled = true;
        if (this.nextBtn) this.nextBtn.disabled = true;
        if (this.submitBtn) this.submitBtn.disabled = true;
    }

    /**
     * Habilita los botones de navegación según el estado
     * @param {number} currentStep - Paso actual
     * @param {number} totalSteps - Total de pasos
     */
    enableNavigation(currentStep, totalSteps) {
        if (this.prevBtn) this.prevBtn.disabled = (currentStep === 1);
        if (this.nextBtn) this.nextBtn.disabled = false;
        if (this.submitBtn) this.submitBtn.disabled = false;
        this.updateButtons(currentStep, totalSteps);
    }

    /**
     * Muestra errores de validación
     * @param {Array} errors - Array de objetos {field, message}
     */
    showValidationErrors(errors) {
        // Limpiar errores previos
        this.clearValidationErrors();

        errors.forEach(error => {
            const element = document.getElementById(error.field);
            if (!element) return;

            // Agregar clase de error
            element.classList.add('is-invalid');

            // Crear mensaje de error
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback d-block';
            errorDiv.textContent = error.message;
            errorDiv.dataset.validationError = error.field;

            // Insertar después del elemento
            element.parentNode.insertBefore(errorDiv, element.nextSibling);
        });
    }

    /**
     * Limpia todos los errores de validación
     */
    clearValidationErrors() {
        // Remover clases de error
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        // Remover mensajes de error
        document.querySelectorAll('[data-validation-error]').forEach(el => {
            el.remove();
        });
    }

    /**
     * Renderiza contenido usando una plantilla
     * @param {string} templateName - Nombre de la plantilla
     * @param {Object} data - Datos para la plantilla
     * @param {string|HTMLElement} container - Contenedor
     */
    render(templateName, data, container) {
        templateManager.render(templateName, data, container);
    }

    /**
     * Muestra un mensaje de alerta
     * @param {string} message - Mensaje a mostrar
     * @param {string} type - Tipo de alerta (success, danger, warning, info)
     */
    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Buscar contenedor de alertas o usar el body
        const alertContainer = document.querySelector('.wizard-alerts') || document.querySelector('.wizard-container');
        if (alertContainer) {
            alertContainer.prepend(alertDiv);

            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    }

    /**
     * Muestra modal de confirmación
     * @param {string} message - Mensaje de confirmación
     * @param {Function} onConfirm - Callback al confirmar
     */
    showConfirmModal(message, onConfirm) {
        const modalId = 'wizardConfirmModal';
        let modal = document.getElementById(modalId);

        if (!modal) {
            // Crear modal si no existe
            modal = this.createConfirmModal(modalId);
            document.body.appendChild(modal);
        }

        // Actualizar mensaje
        const messageElement = modal.querySelector('.modal-body');
        if (messageElement) {
            messageElement.textContent = message;
        }

        // Configurar botón de confirmación
        const confirmBtn = modal.querySelector('.btn-primary');
        if (confirmBtn) {
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener('click', () => {
                if (typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getInstance(modal)?.hide();
                }
                onConfirm();
            });
        }

        // Mostrar modal
        if (typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    /**
     * Crea un modal de confirmación
     * @param {string} modalId - ID del modal
     * @returns {HTMLElement}
     */
    createConfirmModal(modalId) {
        const modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ¿Está seguro de que desea continuar?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary">Confirmar</button>
                    </div>
                </div>
            </div>
        `;
        return modal;
    }

    /**
     * Muestra un spinner de carga en un botón
     * @param {HTMLElement} button - Botón
     * @param {boolean} show - Mostrar u ocultar
     */
    toggleButtonSpinner(button, show) {
        if (!button) return;

        const spinner = button.querySelector('.spinner-border');
        if (show) {
            if (!spinner) {
                const spinnerEl = document.createElement('span');
                spinnerEl.className = 'spinner-border spinner-border-sm me-2';
                button.prepend(spinnerEl);
            }
            button.disabled = true;
        } else {
            if (spinner) {
                spinner.remove();
            }
            button.disabled = false;
        }
    }
}

export default WizardView;
