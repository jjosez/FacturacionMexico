import { SupplierWizardController } from './Supplier/controllers/SupplierWizardController.js';

/**
 * SupplierCfdiWizard
 * Punto de entrada para el wizard de importación de CFDI de proveedor
 */

let supplierWizardController = null;

/**
 * Configuración de validación para el wizard
 */
const validationRules = {
    1: [
        {
            field: 'cfdi-xml',
            required: true,
            message: 'El archivo CFDI XML es obligatorio',
            validator: (value, element) => {
                if (element.files && element.files.length > 0) {
                    return { valid: true };
                }
                return { valid: false, message: 'Debe seleccionar un archivo XML' };
            }
        }
    ]
    // Agregar más reglas según los pasos si es necesario
};

/**
 * Inicializa el wizard de CFDI de proveedor
 */
function initSupplierCfdiWizard() {
    supplierWizardController = new SupplierWizardController({
        totalSteps: document.querySelectorAll('#wizardTabs .nav-link').length,
        formId: 'formCfdiWizard',
        submitLabel: 'Importar',
        validationRules: validationRules,
        tabsSelector: '#wizardTabs .nav-link',
        panesSelector: '.tab-pane',
        prevBtnId: 'prevBtn',
        nextBtnId: 'nextBtn',
        submitBtnId: 'wizardSubmitBtn',
        onSubmit: null // O definir callback personalizado si es necesario
    });

    supplierWizardController.init();
}

// Inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', () => {
    initSupplierCfdiWizard();
});

// Exportar para uso externo si es necesario
export { supplierWizardController };
