import supplierTemplateManager from './SupplierTemplateManager.js';

/**
 * ProductSearchView
 * Vista para el modal de búsqueda y tabla de resultados de productos
 */
export class ProductSearchView {
    constructor({
        modalId = 'product:link:modal',
        searchInputId = 'buscarProductoInput',
        tableBodySelector = '#tablaProductos tbody',
        tableColspan = 5,
        templateManager = supplierTemplateManager
    } = {}) {
        this.modalId = modalId;
        this.searchInputId = searchInputId;
        this.tableBodySelector = tableBodySelector;
        this.tableColspan = tableColspan;
        this.templateManager = templateManager;

        this.dom = {
            modal: document.getElementById(modalId),
            searchInput: document.getElementById(searchInputId),
            tableBody: document.querySelector(tableBodySelector)
        };
    }

    /**
     * Abre el modal de búsqueda
     */
    openModal() {
        if (this.dom.modal && typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(this.dom.modal);
            bsModal.show();
        }
    }

    /**
     * Cierra el modal de búsqueda
     */
    closeModal() {
        if (this.dom.modal && typeof bootstrap !== 'undefined') {
            const bsModal = bootstrap.Modal.getInstance(this.dom.modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    }

    /**
     * Limpia el input de búsqueda
     */
    clearSearch() {
        if (this.dom.searchInput) {
            this.dom.searchInput.value = '';
        }
    }

    /**
     * Renderiza la tabla de productos usando templates
     * @param {Array} productos - Lista de productos a mostrar
     */
    renderProductsTable(productos) {
        if (!this.dom.tableBody) return;

        if (!productos.length) {
            this.renderEmptyState();
            return;
        }

        // Renderizar usando template
        this.templateManager.renderToTbody(
            'product:search:row:template',
            productos,
            this.dom.tableBody
        );
    }

    /**
     * Renderiza el estado vacío usando template
     * @param {string} message - Mensaje personalizado
     */
    renderEmptyState(message = null) {
        if (!this.dom.tableBody) return;

        this.templateManager.renderSingleToTbody(
            'product:search:empty:template',
            { message },
            this.dom.tableBody
        );
    }

    /**
     * Renderiza el estado de carga usando template
     * @param {string} message - Mensaje personalizado
     */
    renderLoadingState(message = null) {
        if (!this.dom.tableBody) return;

        this.templateManager.renderSingleToTbody(
            'product:search:loading:template',
            { message },
            this.dom.tableBody
        );
    }

    /**
     * Renderiza el estado de error usando template
     * @param {string} message - Mensaje de error
     */
    renderErrorState(message = null) {
        if (!this.dom.tableBody) return;

        this.templateManager.renderSingleToTbody(
            'product:search:error:template',
            { message },
            this.dom.tableBody
        );
    }

    /**
     * Obtiene el valor actual del input de búsqueda
     * @returns {string}
     */
    getSearchValue() {
        return this.dom.searchInput?.value.trim() || '';
    }
}
