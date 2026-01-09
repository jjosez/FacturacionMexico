/**
 * ProductSearchService
 * Servicio para búsqueda de productos del proveedor
 */
export class ProductSearchService {
    constructor({ endpoint = 'CfdiSupplierWizard', action = 'search-own-product' } = {}) {
        this.endpoint = endpoint;
        this.action = action;
        this.abortController = null;
    }

    /**
     * Cancela la petición en curso
     */
    abort() {
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = null;
    }

    /**
     * Busca productos por query
     * @param {string} query - Texto de búsqueda
     * @returns {Promise<Array>} Lista de productos encontrados
     */
    async searchProducts(query) {
        // Cancelar búsqueda anterior
        this.abort();
        this.abortController = new AbortController();

        const formData = new FormData();
        formData.append('action', this.action);
        formData.append('query', query);

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                body: formData,
                signal: this.abortController.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return Array.isArray(data) ? data : [];
        } catch (error) {
            if (error.name === 'AbortError') {
                // Búsqueda cancelada, no es un error
                return [];
            }
            console.error('Error al buscar productos:', error);
            throw error;
        }
    }
}
