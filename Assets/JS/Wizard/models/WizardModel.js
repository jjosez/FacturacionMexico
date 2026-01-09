import eventManager from '../core/EventManager.js';

/**
 * WizardModel — Modelo de datos del wizard.
 * Gestiona el estado, validación y navegación entre pasos.
 */
class WizardModel {
    constructor({
        currentStep = 1,
        totalSteps = 1,
        formId = 'wizardForm',
        data = {},
        validationRules = {}
    } = {}) {
        this.currentStep = currentStep;
        this.totalSteps = totalSteps;
        this.formId = formId;
        this.data = data;
        this.validationRules = validationRules;
        this.stepHistory = [currentStep];
        this.isSubmitting = false;
    }

    /**
     * Obtiene el paso actual
     * @returns {number}
     */
    getCurrentStep() {
        return this.currentStep;
    }

    /**
     * Obtiene el total de pasos
     * @returns {number}
     */
    getTotalSteps() {
        return this.totalSteps;
    }

    /**
     * Verifica si es el primer paso
     * @returns {boolean}
     */
    isFirstStep() {
        return this.currentStep === 1;
    }

    /**
     * Verifica si es el último paso
     * @returns {boolean}
     */
    isLastStep() {
        return this.currentStep === this.totalSteps;
    }

    /**
     * Verifica si se puede avanzar al siguiente paso
     * @returns {boolean}
     */
    canGoNext() {
        return this.currentStep < this.totalSteps;
    }

    /**
     * Verifica si se puede retroceder al paso anterior
     * @returns {boolean}
     */
    canGoPrevious() {
        return this.currentStep > 1;
    }

    /**
     * Avanza al siguiente paso
     * @returns {boolean} - true si se pudo avanzar
     */
    next() {
        if (!this.canGoNext()) return false;

        const validationResult = this.validateStep(this.currentStep);
        if (!validationResult.valid) {
            eventManager.emit('wizard:validation:failed', validationResult);
            return false;
        }

        this.currentStep++;
        this.stepHistory.push(this.currentStep);
        eventManager.emit('wizard:step:changed', {
            step: this.currentStep,
            direction: 'next'
        });
        return true;
    }

    /**
     * Retrocede al paso anterior
     * @returns {boolean} - true si se pudo retroceder
     */
    previous() {
        if (!this.canGoPrevious()) return false;

        this.currentStep--;
        this.stepHistory.push(this.currentStep);
        eventManager.emit('wizard:step:changed', {
            step: this.currentStep,
            direction: 'previous'
        });
        return true;
    }

    /**
     * Va a un paso específico
     * @param {number} step - Número de paso
     * @returns {boolean} - true si se pudo cambiar
     */
    goToStep(step) {
        if (step < 1 || step > this.totalSteps || step === this.currentStep) {
            return false;
        }

        this.currentStep = step;
        this.stepHistory.push(this.currentStep);
        eventManager.emit('wizard:step:changed', {
            step: this.currentStep,
            direction: step > this.currentStep ? 'next' : 'previous'
        });
        return true;
    }

    /**
     * Valida un paso específico
     * @param {number} step - Número de paso a validar
     * @returns {{valid: boolean, errors: Array}} - Resultado de validación
     */
    validateStep(step) {
        const rules = this.validationRules[step];
        const errors = [];

        if (!rules || rules.length === 0) {
            return {valid: true, errors: []};
        }

        for (const rule of rules) {
            const element = document.getElementById(rule.field);
            if (!element) continue;

            const value = element.value?.trim() || '';

            // Validación requerido
            if (rule.required && value === '') {
                errors.push({
                    field: rule.field,
                    message: rule.message || `El campo ${rule.field} es obligatorio`
                });
                continue;
            }

            // Validación personalizada
            if (rule.validator && typeof rule.validator === 'function') {
                const customResult = rule.validator(value, element);
                if (!customResult.valid) {
                    errors.push({
                        field: rule.field,
                        message: customResult.message || rule.message
                    });
                }
            }
        }

        return {
            valid: errors.length === 0,
            errors
        };
    }

    /**
     * Actualiza datos del modelo
     * @param {string} key - Clave del dato
     * @param {*} value - Valor del dato
     */
    updateData(key, value) {
        this.data[key] = value;
        eventManager.emit('wizard:data:updated', {key, value});
    }

    /**
     * Obtiene un dato del modelo
     * @param {string} key - Clave del dato
     * @returns {*}
     */
    getData(key) {
        return this.data[key];
    }

    /**
     * Obtiene todos los datos
     * @returns {Object}
     */
    getAllData() {
        return {...this.data};
    }

    /**
     * Reinicia el wizard al estado inicial
     */
    reset() {
        this.currentStep = 1;
        this.data = {};
        this.stepHistory = [1];
        this.isSubmitting = false;
        eventManager.emit('wizard:reset');
    }

    /**
     * Marca el wizard como enviando
     */
    setSubmitting(isSubmitting) {
        this.isSubmitting = isSubmitting;
        eventManager.emit('wizard:submitting:changed', isSubmitting);
    }
}

export default WizardModel;
