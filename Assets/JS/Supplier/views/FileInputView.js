/**
 * FileInputView
 * Vista para los inputs de tipo archivo (custom file inputs)
 */
export class FileInputView {
    constructor({ inputSelector = '.custom-file-input' } = {}) {
        this.inputSelector = inputSelector;
        this.inputs = [];
    }

    /**
     * Conecta los event listeners a los inputs
     */
    connect() {
        this.inputs = document.querySelectorAll(this.inputSelector);

        this.inputs.forEach(input => {
            input.addEventListener('change', (event) => this.handleFileChange(event));
        });
    }

    /**
     * Maneja el cambio de archivo en un input
     * @param {Event} event - Evento de cambio
     */
    handleFileChange(event) {
        const input = event.target;
        const fileName = this.getFileName(input.value);
        const label = input.nextElementSibling;

        if (!label) return;

        // Guardar el título por defecto la primera vez
        if (!label.dataset.defaultTitle) {
            label.dataset.defaultTitle = label.innerHTML;
        }

        if (fileName === '') {
            this.clearFileLabel(label);
        } else {
            this.updateFileLabel(label, fileName);
        }

        // Emitir evento personalizado para que el controller lo maneje
        this.emitFileSelectedEvent(input.id, fileName);
    }

    /**
     * Extrae el nombre del archivo de la ruta completa
     * @param {string} fullPath - Ruta completa del archivo
     * @returns {string} Nombre del archivo
     */
    getFileName(fullPath) {
        return fullPath.split('\\').pop();
    }

    /**
     * Actualiza el label con el nombre del archivo
     * @param {HTMLElement} label - Elemento label
     * @param {string} fileName - Nombre del archivo
     */
    updateFileLabel(label, fileName) {
        label.classList.add('selected');
        label.innerHTML = this.createFileLabelContent(fileName);
    }

    /**
     * Limpia el label al valor por defecto
     * @param {HTMLElement} label - Elemento label
     */
    clearFileLabel(label) {
        label.classList.remove('selected');
        label.innerHTML = label.dataset.defaultTitle || 'Seleccionar archivo';
    }

    /**
     * Crea el contenido del label con el nombre del archivo
     * @param {string} fileName - Nombre del archivo
     * @returns {string} HTML del contenido
     */
    createFileLabelContent(fileName) {
        return `
            <i class="fas fa-file-alt me-2"></i>
            <span class="text-truncate" style="max-width: 200px; display: inline-block;">
                ${this.escapeHtml(fileName)}
            </span>
        `;
    }

    /**
     * Emite un evento personalizado cuando se selecciona un archivo
     * @param {string} inputId - ID del input
     * @param {string} fileName - Nombre del archivo
     */
    emitFileSelectedEvent(inputId, fileName) {
        const event = new CustomEvent('file:selected', {
            detail: {
                inputId: inputId,
                fileName: fileName
            },
            bubbles: true
        });
        document.dispatchEvent(event);
    }

    /**
     * Escapa HTML para prevenir XSS
     * @param {string} text - Texto a escapar
     * @returns {string}
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Resetea todos los inputs de archivo
     */
    resetAll() {
        this.inputs.forEach(input => {
            input.value = '';
            const label = input.nextElementSibling;
            if (label && label.dataset.defaultTitle) {
                this.clearFileLabel(label);
            }
        });
    }

    /**
     * Valida que un input tenga un archivo seleccionado
     * @param {string} inputId - ID del input
     * @returns {boolean}
     */
    hasFile(inputId) {
        const input = document.getElementById(inputId);
        return input && input.files && input.files.length > 0;
    }

    /**
     * Obtiene información del archivo seleccionado
     * @param {string} inputId - ID del input
     * @returns {Object|null} Información del archivo o null
     */
    getFileInfo(inputId) {
        const input = document.getElementById(inputId);
        if (!input || !input.files || !input.files.length) {
            return null;
        }

        const file = input.files[0];
        return {
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified
        };
    }
}
