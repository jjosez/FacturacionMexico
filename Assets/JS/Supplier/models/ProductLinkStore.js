/**
 * ProductLinkStore
 * Almacena el estado de las vinculaciones entre conceptos CFDI y productos
 */
export class ProductLinkStore {
    constructor() {
        // Mapa: index del concepto → referencia del producto
        this.links = new Map();
    }

    /**
     * Verifica si un concepto tiene un producto vinculado
     * @param {string|number} conceptoIndex - Índice del concepto
     * @returns {boolean}
     */
    hasLink(conceptoIndex) {
        return this.links.has(String(conceptoIndex));
    }

    /**
     * Obtiene la referencia del producto vinculado a un concepto
     * @param {string|number} conceptoIndex - Índice del concepto
     * @returns {string|null}
     */
    getLink(conceptoIndex) {
        return this.links.get(String(conceptoIndex)) || null;
    }

    /**
     * Vincula un producto a un concepto
     * @param {string|number} conceptoIndex - Índice del concepto
     * @param {string} referencia - Referencia del producto
     */
    setLink(conceptoIndex, referencia) {
        this.links.set(String(conceptoIndex), referencia);
    }

    /**
     * Desvincula un producto de un concepto
     * @param {string|number} conceptoIndex - Índice del concepto
     */
    removeLink(conceptoIndex) {
        this.links.delete(String(conceptoIndex));
    }

    /**
     * Obtiene todos los enlaces
     * @returns {Map}
     */
    getAllLinks() {
        return new Map(this.links);
    }

    /**
     * Limpia todos los enlaces
     */
    clear() {
        this.links.clear();
    }

    /**
     * Hidrata el store desde el DOM existente
     * Lee los inputs hidden con referencias ya asignadas
     */
    hydrateFromDOM() {
        const inputs = document.querySelectorAll('[id^="concepto-referencia-"]');
        inputs.forEach(input => {
            const match = input.id.match(/concepto-referencia-(\d+)/);
            if (match && input.value) {
                const index = match[1];
                this.setLink(index, input.value);
            }
        });
    }

    /**
     * Obtiene estadísticas de vinculación
     * @returns {Object}
     */
    getStats() {
        return {
            total: this.links.size,
            linked: Array.from(this.links.keys())
        };
    }
}
