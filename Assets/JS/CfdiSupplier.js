/**
 * CfdiSupplier - Gestión de vinculación de productos en CFDI de proveedor
 */
class CfdiSupplier {
    constructor() {
        this.modal = null;
        this.searchInput = null;
        this.tableBody = null;
        this.currentConceptoIndex = null;
        this.searchTimeout = null;
    }

    /**
     * Inicializa el controlador
     */
    init() {
        // Elementos del DOM
        this.modal = document.getElementById('product-link-modal');
        this.modalTitle = document.getElementById('product-link-modal-title');
        this.searchInput = document.getElementById('product-search-input');
        this.tableBody = document.querySelector('#product-table tbody');

        // Event listeners
        this.attachEventListeners();

        console.log('✅ CfdiSupplier inicializado');
    }

    /**
     * Adjunta los event listeners
     */
    attachEventListeners() {
        // Delegación de eventos para botones de vincular
        document.addEventListener('click', (e) => {
            // Botón vincular
            if (e.target.closest('[data-action="product-link"]')) {
                const btn = e.target.closest('[data-action="product-link"]');
                this.openLinkModal(btn.dataset.index, btn.dataset.codigo);
            }

            // Botón seleccionar producto
            if (e.target.closest('[data-action="product-select"]')) {
                const btn = e.target.closest('[data-action="product-select"]');
                this.selectProduct(btn.dataset.referencia);
            }
        });

        // Búsqueda de productos
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
        }
    }

    /**
     * Abre el modal de vinculación
     */
    openLinkModal(index, codigo = '') {
        this.currentConceptoIndex = index;

        // Actualizar título del modal
        if (this.modalTitle) {
            const codigoText = codigo ? ` - <span class="text-muted">${this.escapeHtml(codigo)}</span>` : '';
            this.modalTitle.innerHTML = `<i class="fas fa-link"></i> Vincular Producto${codigoText}`;
        }

        // Limpiar búsqueda
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        this.renderEmptyState();

        // Abrir modal
        if (this.modal && typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(this.modal);
            bsModal.show();
        }
    }

    /**
     * Maneja la búsqueda de productos con debounce
     */
    handleSearch(query) {
        clearTimeout(this.searchTimeout);

        if (query.length < 2) {
            this.renderEmptyState();
            return;
        }

        this.searchTimeout = setTimeout(() => {
            this.searchProducts(query);
        }, 300);
    }

    /**
     * Busca productos en el servidor
     */
    async searchProducts(query) {
        this.renderLoadingState();

        try {
            const formData = new FormData();
            formData.append('action', 'search-products');
            formData.append('query', query);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Error en la búsqueda');
            }

            const data = await response.json();
            this.renderProducts(data.products || []);
        } catch (error) {
            console.error('Error buscando productos:', error);
            this.renderErrorState();
        }
    }

    /**
     * Selecciona un producto y lo vincula
     */
    async selectProduct(referencia) {
        if (!this.currentConceptoIndex) {
            console.warn('No hay concepto seleccionado');
            return;
        }

        // Obtener datos del concepto desde el TR
        const row = document.querySelector(`tr[data-concepto-index="${this.currentConceptoIndex}"]`);
        if (!row) {
            console.error('No se encontró la fila del concepto');
            return;
        }

        let conceptoData = {};
        try {
            conceptoData = JSON.parse(row.dataset.concepto || '{}');
        } catch (e) {
            console.error('Error al parsear datos del concepto:', e);
        }

        try {
            const formData = new FormData();
            formData.append('action', 'link-product');
            formData.append('index', this.currentConceptoIndex);
            formData.append('referencia', referencia);
            formData.append('refproveedor', conceptoData.refproveedor || '');
            formData.append('precio', conceptoData.precio || '0');
            formData.append('cantidad', conceptoData.cantidad || '0');
            formData.append('codproveedor', conceptoData.codproveedor || '');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Error al vincular producto');
            }

            const data = await response.json();

            if (data.ok) {
                // Actualizar UI
                this.updateConceptoRow(this.currentConceptoIndex, referencia);

                // Cerrar modal
                this.closeModal();

                // Mostrar notificación
                this.showNotification('Producto vinculado correctamente', 'success');
            } else {
                throw new Error(data.message || 'Error al vincular');
            }
        } catch (error) {
            console.error('Error vinculando producto:', error);
            this.showNotification('Error al vincular producto', 'danger');
        }
    }

    /**
     * Actualiza la fila del concepto con el producto vinculado
     */
    updateConceptoRow(index, referencia) {
        const row = document.querySelector(`tr[data-concepto-index="${index}"]`);
        if (!row) return;

        const cell = row.querySelector('.producto-vinculado');
        if (cell) {
            cell.innerHTML = `
                <span class="badge bg-success">
                    <i class="fas fa-check"></i> ${referencia}
                </span>
            `;
        }

        const btn = row.querySelector('[data-action="product-link"]');
        if (btn) {
            btn.className = 'btn btn-sm btn-outline-secondary';
            btn.innerHTML = '<i class="fas fa-link"></i> Cambiar';
        }
    }

    /**
     * Renderiza la lista de productos
     */
    renderProducts(products) {
        if (!this.tableBody) return;

        if (products.length === 0) {
            this.renderEmptyState('No se encontraron productos');
            return;
        }

        const html = products.map(p => `
            <tr>
                <td>
                    <strong>${this.escapeHtml(p.referencia)}</strong>
                    ${p.referencia_fabricante ? `<br><small class="text-muted">Ref. Fab: ${this.escapeHtml(p.referencia_fabricante)}</small>` : ''}
                </td>
                <td>${this.escapeHtml(p.descripcion)}</td>
                <td>${this.escapeHtml(p.codfabricante || '—')}</td>
                <td class="text-end">${this.formatPrice(p.precio)}</td>
                <td class="text-center">
                    <button type="button"
                            class="btn btn-sm btn-success"
                            data-action="product-select"
                            data-referencia="${this.escapeHtml(p.referencia)}">
                        <i class="fas fa-check"></i> Seleccionar
                    </button>
                </td>
            </tr>
        `).join('');

        this.tableBody.innerHTML = html;
    }

    /**
     * Formatea un precio
     */
    formatPrice(price) {
        const num = parseFloat(price) || 0;
        return num.toFixed(2);
    }

    /**
     * Renderiza estado vacío
     */
    renderEmptyState(message = 'Escribe para buscar productos') {
        if (!this.tableBody) return;

        this.tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-4">
                    <i class="fas fa-search fa-2x mb-2"></i>
                    <p>${message}</p>
                </td>
            </tr>
        `;
    }

    /**
     * Renderiza estado de carga
     */
    renderLoadingState() {
        if (!this.tableBody) return;

        this.tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <span class="ms-2">Buscando productos...</span>
                </td>
            </tr>
        `;
    }

    /**
     * Renderiza estado de error
     */
    renderErrorState() {
        if (!this.tableBody) return;

        this.tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <p>Error al buscar productos</p>
                </td>
            </tr>
        `;
    }

    /**
     * Cierra el modal
     */
    closeModal() {
        if (this.modal && typeof bootstrap !== 'undefined') {
            const bsModal = bootstrap.Modal.getInstance(this.modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
        this.currentConceptoIndex = null;
    }

    /**
     * Muestra una notificación
     */
    showNotification(message, type = 'info') {
        // Implementar con el sistema de notificaciones de FacturaScripts
        // o usar un simple alert temporal
        console.log(`[${type}] ${message}`);
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    const cfdiSupplier = new CfdiSupplier();
    cfdiSupplier.init();
});
