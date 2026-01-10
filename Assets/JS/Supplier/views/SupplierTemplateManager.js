import { Eta } from '../../vendor/eta/core.js';

/**
 * SupplierTemplateManager
 * Gestor de plantillas para el módulo Supplier
 */
class SupplierTemplateManager {
    constructor() {
        this.templates = {};
        this.eta = null;
        this.initialized = false;
    }

    /**
     * Inicializa el motor Eta y carga templates del DOM
     */
    async init() {
        if (this.initialized) return;

        // Inicializar Eta
        this.eta = new Eta({ useWith: true });

        // Cargar templates del DOM
        this.loadTemplatesFromDOM();

        this.initialized = true;
    }

    /**
     * Carga templates desde el DOM con el prefijo 'product:'
     */
    loadTemplatesFromDOM() {
        const scriptTemplates = document.querySelectorAll('script[type="text/template"][id^="product:"]');

        scriptTemplates.forEach((tpl) => {
            const id = tpl.id;
            this.templates[id] = tpl.innerHTML.trim();
        });

        console.log(`✅ Cargados ${scriptTemplates.length} templates para Supplier`);
    }

    /**
     * Renderiza una fila completa (<tr>) con template
     * @param {string} templateName - Nombre del template
     * @param {Object} data - Datos para renderizar
     * @returns {string} HTML renderizado como <tr>
     */
    renderRow(templateName, data = {}) {
        if (!this.initialized) {
            console.error('❌ TemplateManager no inicializado');
            return '';
        }

        const template = this.templates[templateName];
        if (!template) {
            console.error(`❌ Template "${templateName}" no encontrado`);
            return '';
        }

        try {
            const content = this.eta.renderString(template, data);
            return `<tr>${content}</tr>`;
        } catch (error) {
            console.error(`❌ Error renderizando template "${templateName}":`, error);
            return '';
        }
    }

    /**
     * Renderiza múltiples filas
     * @param {string} templateName - Nombre del template
     * @param {Array} dataArray - Array de objetos con datos
     * @returns {string} HTML renderizado con múltiples <tr>
     */
    renderRows(templateName, dataArray = []) {
        if (!Array.isArray(dataArray)) {
            console.error('❌ dataArray debe ser un array');
            return '';
        }

        return dataArray.map(data => this.renderRow(templateName, data)).join('');
    }

    /**
     * Renderiza directamente en un tbody
     * @param {string} templateName - Nombre del template
     * @param {Array} dataArray - Array de objetos
     * @param {HTMLElement} tbody - Elemento tbody
     */
    renderToTbody(templateName, dataArray, tbody) {
        if (!tbody) {
            console.error('❌ tbody no proporcionado');
            return;
        }

        const html = this.renderRows(templateName, dataArray);
        tbody.innerHTML = html;
    }

    /**
     * Renderiza una sola fila directamente en tbody
     * @param {string} templateName - Nombre del template
     * @param {Object} data - Datos
     * @param {HTMLElement} tbody - Elemento tbody
     */
    renderSingleToTbody(templateName, data, tbody) {
        if (!tbody) {
            console.error('❌ tbody no proporcionado');
            return;
        }

        const html = this.renderRow(templateName, data);
        tbody.innerHTML = html;
    }

    /**
     * Verifica si un template existe
     * @param {string} templateName - Nombre del template
     * @returns {boolean}
     */
    hasTemplate(templateName) {
        return !!this.templates[templateName];
    }

    /**
     * Obtiene la lista de templates cargados
     * @returns {Array<string>}
     */
    getTemplateNames() {
        return Object.keys(this.templates);
    }
}

// Exportar instancia singleton
const supplierTemplateManager = new SupplierTemplateManager();
export default supplierTemplateManager;
