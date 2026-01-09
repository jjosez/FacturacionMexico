import WizardModel from '../models/WizardModel.js';
import WizardView from '../views/WizardView.js';
import eventDispatcher from '../core/EventDispatcher.js';
import eventManager from '../core/EventManager.js';

/**
 * WizardController — Controlador principal del wizard.
 * Conecta el modelo, la vista y los eventos.
 */
class WizardController {
    constructor({
        totalSteps = 1,
        formId = 'wizardForm',
        submitLabel = 'Finalizar',
        validationRules = {},
        viewConfig = {},
        onSubmit = null
    } = {}) {
        this.model = new WizardModel({
            totalSteps,
            formId,
            validationRules
        });

        this.view = new WizardView(viewConfig);
        this.submitLabel = submitLabel;
        this.onSubmitCallback = onSubmit;
        this.isInitialized = false;
    }

    /**
     * Inicializa el controlador
     */
    init() {
        if (this.isInitialized) return;

        this.view.updateSubmitButtonLabel(this.submitLabel);
        this.view.showStep(this.model.getCurrentStep());
        this.view.updateButtons(this.model.getCurrentStep(), this.model.getTotalSteps());

        this.registerEventHandlers();
        this.registerDOMEventHandlers();
        this.isInitialized = true;

        eventManager.emit('wizard:initialized', this.model);
    }

    /**
     * Registra manejadores de eventos del EventDispatcher
     */
    registerEventHandlers() {
        eventDispatcher.register('wizard:step:next', this.handleNext.bind(this));
        eventDispatcher.register('wizard:step:previous', this.handlePrevious.bind(this));
        eventDispatcher.register('wizard:step:goto', this.handleGoTo.bind(this));
        eventDispatcher.register('wizard:submit', this.handleSubmit.bind(this));
        eventDispatcher.register('wizard:reset', this.handleReset.bind(this));
    }

    /**
     * Registra manejadores de eventos del EventManager
     */
    registerDOMEventHandlers() {
        // Escuchar cambios de paso
        eventManager.on('wizard:step:changed', this.onStepChanged.bind(this));

        // Escuchar errores de validación
        eventManager.on('wizard:validation:failed', this.onValidationFailed.bind(this));

        // Escuchar cambios en el estado de envío
        eventManager.on('wizard:submitting:changed', this.onSubmittingChanged.bind(this));

        // Escuchar cambios en datos
        eventManager.on('wizard:data:updated', this.onDataUpdated.bind(this));

        // Listener para tabs de Bootstrap
        this.view.tabs.forEach((tab, index) => {
            tab.addEventListener('shown.bs.tab', () => {
                const newStep = index + 1;
                if (newStep !== this.model.getCurrentStep()) {
                    this.model.goToStep(newStep);
                }
            });
        });

        // Listener para cambios en campos del formulario
        document.addEventListener('change', (event) => {
            const {action, field} = event.target.dataset;
            if (action === 'wizard:field:update' && field) {
                this.model.updateData(field, event.target.value);
            }
        });
    }

    /**
     * Maneja el evento de siguiente paso
     */
    handleNext(el) {
        const success = this.model.next();
        if (success) {
            this.view.showStep(this.model.getCurrentStep());
        }
    }

    /**
     * Maneja el evento de paso anterior
     */
    handlePrevious(el) {
        const success = this.model.previous();
        if (success) {
            this.view.showStep(this.model.getCurrentStep());
        }
    }

    /**
     * Maneja el evento de ir a un paso específico
     * @param {HTMLElement} el - Elemento con data-step
     */
    handleGoTo(el) {
        const step = parseInt(el.dataset.step, 10);
        if (isNaN(step)) return;

        const success = this.model.goToStep(step);
        if (success) {
            this.view.showStep(this.model.getCurrentStep());
        }
    }

    /**
     * Maneja el evento de envío del formulario
     */
    handleSubmit(el) {
        // Validar paso final
        const validationResult = this.model.validateStep(this.model.getCurrentStep());
        if (!validationResult.valid) {
            eventManager.emit('wizard:validation:failed', validationResult);
            return;
        }

        // Mostrar confirmación si está configurada
        const confirmMessage = el.dataset.confirmMessage ||
            'Esta acción necesita confirmación. ¿Está seguro de que desea continuar?';

        this.view.showConfirmModal(confirmMessage, () => {
            this.submitForm();
        });
    }

    /**
     * Envía el formulario
     */
    async submitForm() {
        const form = document.getElementById(this.model.formId);
        if (!form) {
            console.error('❌ Formulario no encontrado:', this.model.formId);
            return;
        }

        this.model.setSubmitting(true);

        try {
            if (this.onSubmitCallback && typeof this.onSubmitCallback === 'function') {
                await this.onSubmitCallback(this.model.getAllData(), form);
            } else {
                form.submit();
            }
            eventManager.emit('wizard:submitted', this.model.getAllData());
        } catch (error) {
            console.error('❌ Error al enviar el formulario:', error);
            this.view.showAlert('Error al enviar el formulario', 'danger');
            eventManager.emit('wizard:submit:error', error);
        } finally {
            this.model.setSubmitting(false);
        }
    }

    /**
     * Maneja el evento de reinicio
     */
    handleReset(el) {
        this.model.reset();
        this.view.showStep(1);
        this.view.clearValidationErrors();
    }

    /**
     * Callback cuando cambia el paso
     * @param {Object} data - {step, direction}
     */
    onStepChanged(data) {
        this.view.updateButtons(data.step, this.model.getTotalSteps());
        this.view.clearValidationErrors();
    }

    /**
     * Callback cuando falla la validación
     * @param {Object} result - {valid, errors}
     */
    onValidationFailed(result) {
        this.view.showValidationErrors(result.errors);
        this.view.showAlert('Por favor, corrija los errores antes de continuar', 'warning');
    }

    /**
     * Callback cuando cambia el estado de envío
     * @param {boolean} isSubmitting
     */
    onSubmittingChanged(isSubmitting) {
        if (isSubmitting) {
            this.view.disableNavigation();
            if (this.view.submitBtn) {
                this.view.toggleButtonSpinner(this.view.submitBtn, true);
            }
        } else {
            this.view.enableNavigation(
                this.model.getCurrentStep(),
                this.model.getTotalSteps()
            );
            if (this.view.submitBtn) {
                this.view.toggleButtonSpinner(this.view.submitBtn, false);
            }
        }
    }

    /**
     * Callback cuando se actualizan datos
     * @param {Object} data - {key, value}
     */
    onDataUpdated(data) {
        // Puede ser usado para persistencia o efectos secundarios
        eventManager.emit('wizard:field:changed', data);
    }

    /**
     * Obtiene el estado actual del modelo
     * @returns {WizardModel}
     */
    getState() {
        return this.model;
    }

    /**
     * Destruye el controlador y limpia listeners
     */
    destroy() {
        eventDispatcher.clear();
        eventManager.clear();
        this.isInitialized = false;
        eventManager.emit('wizard:destroyed');
    }
}

export default WizardController;
