/**
 * TemplateManager — Gestor de plantillas HTML.
 * Carga, registra y renderiza plantillas usando el motor Eta.
 */
class TemplateManager {
    constructor(templateMap = {}) {
        this.templates = templateMap;
        this.viewCache = {};
        this.eta = null;
    }

    /**
     * Inicializa el motor de plantillas Eta
     * @param {Object} Eta - Clase Eta importada
     */
    async initEngine(Eta) {
        if (!this.eta) {
            this.eta = new Eta({useWith: true});
        }
        return this.eta;
    }

    /**
     * Asignar plantilla manualmente
     * @param {string} name - Nombre de la plantilla
     * @param {string} htmlString - Contenido HTML de la plantilla
     */
    registerTemplate(name, htmlString) {
        this.templates[name] = htmlString;
    }

    /**
     * Carga plantillas desde el DOM al inicializar
     * @param {string} prefix - Prefijo opcional para los IDs
     */
    preloadTemplatesFromDOM(prefix = '') {
        const scriptTemplates = document.querySelectorAll('script[type="text/template"]');

        scriptTemplates.forEach((tpl) => {
            const id = tpl.id.replace(prefix, '');
            this.templates[id] = tpl.innerHTML;
        });
    }

    /**
     * Renderiza una plantilla con datos en un contenedor
     * @param {string} templateName - Clave de la plantilla
     * @param {Object} data - Datos para renderizar
     * @param {HTMLElement|string} container - Contenedor DOM o id
     */
    render(templateName, data = {}, container) {
        if (!this.eta) {
            console.error('❌ Eta engine not initialized. Call initEngine() first.');
            return;
        }

        const template = this.templates[templateName];
        if (!template) {
            console.error(`❌ Template "${templateName}" not found.`);
            return;
        }

        let target = container;
        if (typeof container === 'string') {
            target = this.viewCache[container] || document.getElementById(container);
            if (target) this.viewCache[container] = target;
        }

        if (!target) {
            console.error(`❌ Container for "${templateName}" not found.`);
            return;
        }

        try {
            target.innerHTML = this.eta.renderString(template, data);
        } catch (error) {
            console.error(`❌ Error rendering template "${templateName}":`, error);
        }
    }

    /**
     * Renderiza y devuelve el HTML como string
     * @param {string} templateName - Clave de la plantilla
     * @param {Object} data - Datos para renderizar
     * @returns {string} - HTML renderizado
     */
    renderToString(templateName, data = {}) {
        if (!this.eta) {
            console.error('❌ Eta engine not initialized. Call initEngine() first.');
            return '';
        }

        const template = this.templates[templateName];
        if (!template) {
            console.error(`❌ Template "${templateName}" not found.`);
            return '';
        }

        try {
            return this.eta.renderString(template, data);
        } catch (error) {
            console.error(`❌ Error rendering template "${templateName}":`, error);
            return '';
        }
    }

    /**
     * Obtiene una plantilla registrada
     * @param {string} templateName - Nombre de la plantilla
     * @returns {string|null}
     */
    getTemplate(templateName) {
        return this.templates[templateName] || null;
    }

    /**
     * Verifica si una plantilla existe
     * @param {string} templateName - Nombre de la plantilla
     * @returns {boolean}
     */
    hasTemplate(templateName) {
        return !!this.templates[templateName];
    }

    /**
     * Elimina una plantilla del registro
     * @param {string} templateName - Nombre de la plantilla
     */
    removeTemplate(templateName) {
        delete this.templates[templateName];
    }

    /**
     * Limpia la caché de vistas
     */
    clearViewCache() {
        this.viewCache = {};
    }
}

// Exportar instancia singleton
const templateManager = new TemplateManager({});
export default templateManager;
