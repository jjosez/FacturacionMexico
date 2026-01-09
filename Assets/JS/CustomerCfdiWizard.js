import WizardController from './Wizard/controllers/WizardController.js';
import eventDispatcher from './Wizard/core/EventDispatcher.js';
import eventManager from './Wizard/core/EventManager.js';
import {RelatedController} from './CFDI/controllers/RelatedController.js';

let relatedController = null;
let wizardController = null;

/**
 * Inicializa el controlador de CFDIs relacionados
 */
function initRelatedController() {
    if (relatedController) return;

    const relatedTbody = document.getElementById('cfdi:related:list:view');
    const searchTbody = document.getElementById('cfdi:search:list:view');
    if (!relatedTbody || !searchTbody) return;

    relatedController = new RelatedController({
        endpoint: 'EditCfdiCliente',
        action: 'search-related-cfdis',
        fetchDebounceMs: 250,
        toastDelayMs: 3500,
        searchColspan: 7,
    });

    relatedController.connect();
}

/**
 * Configuración de validación para el wizard
 */
const validationRules = {
    1: [
        {
            field: 'codcliente',
            required: true,
            message: 'El cliente es obligatorio'
        }
    ]
    // Agregar más reglas según los pasos
};

/**
 * Inicializa el wizard de CFDI de cliente
 */
function initCustomerCfdiWizard() {
    wizardController = new WizardController({
        totalSteps: document.querySelectorAll('#wizardTabs .nav-link').length,
        formId: 'formCfdiWizard',
        submitLabel: 'Timbrar',
        validationRules: validationRules,
        viewConfig: {
            tabsSelector: '#wizardTabs .nav-link',
            panesSelector: '.tab-pane',
            prevBtnId: 'prevBtn',
            nextBtnId: 'nextBtn',
            submitBtnId: 'wizardSubmitBtn'
        }
    });

    // Inicializar EventDispatcher para escuchar clics
    eventDispatcher.listen();

    // Inicializar el wizard
    wizardController.init();

    // Listeners personalizados para el CFDI de cliente
    registerCustomEvents();
}

/**
 * Registra eventos personalizados para el CFDI de cliente
 */
function registerCustomEvents() {
    // Evento cuando el wizard se inicializa
    eventManager.on('wizard:initialized', (model) => {
        console.log('Wizard CFDI Cliente inicializado', model);
    });

    // Evento cuando cambia de paso
    eventManager.on('wizard:step:changed', (data) => {
        console.log('Paso cambiado:', data.step, 'Dirección:', data.direction);
    });

    // Evento cuando se valida y falla
    eventManager.on('wizard:validation:failed', (result) => {
        console.warn('Validación fallida:', result.errors);
    });

    // Evento cuando se envía el formulario
    eventManager.on('wizard:submitted', (data) => {
        console.log('Formulario enviado con datos:', data);
    });

    // Evento cuando hay error al enviar
    eventManager.on('wizard:submit:error', (error) => {
        console.error('Error al enviar:', error);
    });
}

// Inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', () => {
    initCustomerCfdiWizard();
    initRelatedController();
});

// Exportar para uso externo si es necesario
export {wizardController, relatedController};
