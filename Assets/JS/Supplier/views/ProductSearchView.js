/**
 * ProductSearchView
 * Vista para el modal de búsqueda y tabla de resultados de productos
 */
export class ProductSearchView {
    constructor({
        modalId = 'product:link:modal',
        searchInputId = 'buscarProductoInput',
        tableBodySelector = '#tablaProductos tbody',
        tableColspan = 4
    } = {}) {
        this.modalId = modalId;
        this.searchInputId = searchInputId;
        this.tableBodySelector = tableBodySelector;
        this.tableColspan = tableColspan;

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
     * Renderiza la tabla de productos
     * @param {Array} productos - Lista de productos a mostrar
     */
    renderProductsTable(productos) {
        if (!this.dom.tableBody) return;

        this.dom.tableBody.innerHTML = '';

        if (!productos.length) {
            this.renderEmptyState();
            return;
        }

        productos.forEach(producto => {
            const row = this.createProductRow(producto);
            this.dom.tableBody.insertAdjacentHTML('beforeend', row);
        });
    }

    /**
     * Renderiza el estado vacío
     */
    renderEmptyState() {
        if (!this.dom.tableBody) return;

        this.dom.tableBody.innerHTML = `
            <tr>
                <td colspan="${this.tableColspan}" class="text-center text-muted">
                    No se encontraron productos
                </td>
            </tr>
        `;
    }

    /**
     * Renderiza el estado de carga
     */
    renderLoadingState() {
        if (!this.dom.tableBody) return;

        this.dom.tableBody.innerHTML = `
            <tr>
                <td colspan="${this.tableColspan}" class="text-center">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <span class="ms-2">Buscando productos...</span>
                </td>
            </tr>
        `;
    }

    /**
     * Renderiza el estado de error
     * @param {string} message - Mensaje de error
     */
    renderErrorState(message = 'Error al buscar productos') {
        if (!this.dom.tableBody) return;

        this.dom.tableBody.innerHTML = `
            <tr>
                <td colspan="${this.tableColspan}" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="ms-2">${message}</span>
                </td>
            </tr>
        `;
    }

    /**
     * Crea el HTML de una fila de producto
     * @param {Object} producto - Datos del producto
     * @returns {string} HTML de la fila
     */
    createProductRow(producto) {
        const referencia = this.escapeHtml(producto.referencia || '');
        const codfabricante = this.escapeHtml(producto.codfabricante || '');
        const refFabricante = this.escapeHtml(producto.referencia_fabricante || producto.refproveedor || '—');
        const descripcion = this.escapeHtml(producto.descripcion || '');

        return `
            <tr>
                <td>${referencia}</td>
                <td>${refFabricante}</td>
                <td>${codfabricante}</td>
                <td>${descripcion}</td>
                <td>
                    <button type="button"
                        class="btn btn-sm btn-success"
                        data-action="product:select"
                        data-referencia="${referencia}">
                        Seleccionar
                    </button>
                </td>
            </tr>
        `;
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
     * Obtiene el valor actual del input de búsqueda
     * @returns {string}
     */
    getSearchValue() {
        return this.dom.searchInput?.value.trim() || '';
    }
}
