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
            tableBody: document.querySelector(tableBodySelector),
            clearButton: document.getElementById('clearProductSearch'),
            status: document.getElementById('productSearchStatus')
        };

        this.onClearSearch = this.onClearSearch.bind(this);
        this.dom.clearButton?.addEventListener('click', this.onClearSearch);
    }

    /**
     * Abre el modal de búsqueda
     * @param {Object} options - Opciones adicionales
     * @param {string} options.code - Código del concepto (folio)
     * @param {string} options.ref - Referencia del proveedor
     */
    openModal(options = {}) {
        if (this.dom.modal && typeof bootstrap !== 'undefined') {
            // Actualizar título con información del concepto
            const codeEl = document.getElementById('product:link:modal:code');
            const refEl = document.getElementById('product:link:modal:ref');
            if (codeEl) {
                codeEl.textContent = options.code ? ': #' + options.code : '';
            }
            if (refEl) {
                refEl.textContent = options.ref ? ' | Referencia CFDI: ' + options.ref : '';
            }

            const bsModal = new bootstrap.Modal(this.dom.modal);
            this.dom.modal.addEventListener('shown.bs.modal', () => this.dom.searchInput?.focus(), {once: true});
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
        this.updateStatus('');
    }

    onClearSearch() {
        this.clearSearch();
        this.renderEmptyState('Escribe al menos 2 caracteres para buscar.');
        this.dom.searchInput?.focus();
    }

    updateStatus(message) {
        if (this.dom.status) {
            this.dom.status.textContent = message;
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

        this.updateStatus(`${productos.length} producto${productos.length === 1 ? '' : 's'} encontrado${productos.length === 1 ? '' : 's'}`);

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

        this.updateStatus('');

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

        this.updateStatus('Buscando...');

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

        this.updateStatus('No se pudo completar la búsqueda');

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
