import WizardController from '../../Wizard/controllers/WizardController.js';
import eventDispatcher from '../../Wizard/core/EventDispatcher.js';
import eventManager from '../../Wizard/core/EventManager.js';
import { ProductLinkController } from './ProductLinkController.js';
import { FileInputView } from '../views/FileInputView.js';
import supplierTemplateManager from '../views/SupplierTemplateManager.js';

/**
 * SupplierWizardController
 * Controlador principal para el wizard de CFDI de proveedor
 */
export class SupplierWizardController {
    constructor(options = {}) {
        // Configuración del wizard
        this.wizardConfig = {
            totalSteps: options.totalSteps ?? 3,
            formId: options.formId ?? 'formCfdiWizard',
            submitLabel: options.submitLabel ?? 'Importar',
            validationRules: options.validationRules ?? {},
            viewConfig: {
                tabsSelector: options.tabsSelector ?? '#wizardTabs .nav-link',
                panesSelector: options.panesSelector ?? '.tab-pane',
                prevBtnId: options.prevBtnId ?? 'prevBtn',
                nextBtnId: options.nextBtnId ?? 'nextBtn',
                submitBtnId: options.submitBtnId ?? 'wizardSubmitBtn'
            }
        };

        // Controladores
        this.wizardController = null;
        this.productLinkController = null;
        this.fileInputView = null;
        this.templateManager = supplierTemplateManager;

        // Callbacks personalizados
        this.onSubmitCallback = options.onSubmit ?? null;
    }

    /**
     * Inicializa el controlador principal de manera asíncrona
     */
    async init() {
        // Inicializar templates primero
        await this.initTemplates();

        // Inicializar wizard base
        this.initWizard();

        // Inicializar controlador de productos
        this.initProductLinkController();

        // Inicializar vista de archivos
        this.initFileInputView();

        // Inicializar EventDispatcher
        eventDispatcher.listen();

        // Registrar eventos personalizados
        this.registerWizardEvents();
        this.registerCustomEvents();
    }

    /**
     * Inicializa el gestor de templates
     */
    async initTemplates() {
        await this.templateManager.init();
        console.log('✅ Templates inicializados');
    }

    /**
     * Inicializa el wizard base
     */
    initWizard() {
        this.wizardController = new WizardController({
            ...this.wizardConfig,
            onSubmit: this.onSubmitCallback
        });

        this.wizardController.init();
    }

    /**
     * Inicializa el controlador de vinculación de productos
     */
    initProductLinkController() {
        this.productLinkController = new ProductLinkController({
            endpoint: 'CfdiSupplierWizard',
            action: 'search-own-product',
            searchDebounceMs: 300
        });

        this.productLinkController.connect();
    }

    /**
     * Inicializa la vista de inputs de archivo
     */
    initFileInputView() {
        this.fileInputView = new FileInputView({
            inputSelector: '.custom-file-input'
        });

        this.fileInputView.connect();
    }

    /**
     * Registra eventos del wizard
     */
    registerWizardEvents() {
        eventManager.on('wizard:initialized', (model) => {
            console.log('✅ Wizard CFDI Proveedor inicializado', model);
        });

        eventManager.on('wizard:step:changed', (data) => {
            console.log(`📍 Paso cambiado: ${data.step} (${data.direction})`);
            this.onStepChanged(data);
        });

        eventManager.on('wizard:validation:failed', (result) => {
            console.warn('⚠️ Validación fallida:', result.errors);
        });

        eventManager.on('wizard:submitted', (data) => {
            console.log('📤 Formulario enviado:', data);
            this.onFormSubmitted(data);
        });

        eventManager.on('wizard:submit:error', (error) => {
            console.error('❌ Error al enviar:', error);
        });
    }

    /**
     * Registra eventos personalizados del supplier
     */
    registerCustomEvents() {
        // Eventos de vinculación de productos
        eventManager.on('product:linked', (data) => {
            console.log(`🔗 Producto vinculado: concepto ${data.index} → ${data.referencia}`);
        });

        eventManager.on('product:unlinked', (data) => {
            console.log(`🔓 Producto desvinculado: concepto ${data.index}`);
        });

        eventManager.on('products:searched', (data) => {
            console.log(`🔍 Búsqueda realizada: "${data.query}" (${data.count} resultados)`);
        });

        // Eventos de archivos
        document.addEventListener('file:selected', (event) => {
            console.log(`📎 Archivo seleccionado: ${event.detail.fileName}`);
        });
    }

    /**
     * Callback cuando cambia el paso del wizard
     * @param {Object} data - {step, direction}
     */
    onStepChanged(data) {
        // Lógica adicional cuando cambia de paso
        if (data.step === 2) {
            // Paso de productos: actualizar estadísticas
            const stats = this.productLinkController.getStats();
            console.log(`📊 Productos vinculados: ${stats.total}`);
        }
    }

    /**
     * Callback cuando se envía el formulario
     * @param {Object} data - Datos del formulario
     */
    onFormSubmitted(data) {
        // Validaciones adicionales antes de enviar
        const stats = this.productLinkController.getStats();
        console.log(`✅ Enviando con ${stats.total} productos vinculados`);
    }

    /**
     * Obtiene el estado actual del wizard
     * @returns {Object}
     */
    getState() {
        return {
            wizard: this.wizardController?.getState(),
            productLinks: this.productLinkController?.getStats()
        };
    }

    /**
     * Resetea el wizard y todos los controladores
     */
    reset() {
        if (this.wizardController) {
            this.wizardController.handleReset();
        }

        if (this.productLinkController) {
            this.productLinkController.store.clear();
            this.productLinkController.updateAllButtonStates();
        }

        if (this.fileInputView) {
            this.fileInputView.resetAll();
        }
    }

    /**
     * Destruye el controlador y limpia recursos
     */
    destroy() {
        if (this.wizardController) {
            this.wizardController.destroy();
        }

        if (this.productLinkController) {
            this.productLinkController.disconnect();
        }

        eventDispatcher.clear();
        eventManager.clear();

        console.log('🗑️ SupplierWizardController destruido');
    }
}
