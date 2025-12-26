import {Eta} from "../../vendor/eta/core.js";

const eta = new Eta({ useWith: true });

class TemplateManager {
    constructor() {
        this.templates = {};
        this.preloadTemplatesFromDOM();
    }

    /**
     * Carga plantillas desde el DOM al inicializar
     */
    preloadTemplatesFromDOM() {
        const scriptTemplates = document.querySelectorAll('script[type="text/template"]');
        scriptTemplates.forEach((tpl) => {
            if (tpl.id) {
                this.templates[tpl.id] = tpl.innerHTML;
            }
        });
    }

    /**
     * Registrar plantilla manualmente
     */
    registerTemplate(name, htmlString) {
        this.templates[name] = htmlString;
    }

    /**
     * Renderiza y devuelve el HTML como string
     */
    renderToString(templateName, data = {}) {
        const template = this.templates[templateName];
        if (!template) {
            console.error(`❌ Template "${templateName}" not found.`);
            return '';
        }
        return eta.renderString(template, data);
    }

    /**
     * Renderiza directamente en un contenedor
     */
    render(templateName, data = {}, container) {
        const html = this.renderToString(templateName, data);

        let target = container;
        if (typeof container === 'string') {
            target = document.getElementById(container);
        }

        if (!target) {
            console.error(`❌ Container for "${templateName}" not found.`);
            return;
        }

        target.innerHTML = html;
    }
}

const cfdiTemplates = new TemplateManager();
export default cfdiTemplates;
