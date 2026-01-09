import eventDispatcher from '../../Wizard/core/EventDispatcher.js';
import eventManager from '../../Wizard/core/EventManager.js';
import { ProductSearchService } from '../services/ProductSearchService.js';
import { ProductSearchView } from '../views/ProductSearchView.js';
import { ProductLinkView } from '../views/ProductLinkView.js';
import { ProductLinkStore } from '../models/ProductLinkStore.js';

/**
 * ProductLinkController
 * Controlador para la vinculación de productos a conceptos CFDI
 */
export class ProductLinkController {
    constructor(options = {}) {
        // Servicios y dependencias
        this.service = options.service ?? new ProductSearchService(options);
        this.searchView = options.searchView ?? new ProductSearchView(options);
        this.linkView = options.linkView ?? new ProductLinkView();
        this.store = options.store ?? new ProductLinkStore();

        // Estado interno
        this.currentConceptoIndex = null;
        this.searchDebounceMs = options.searchDebounceMs ?? 300;

        // Bind de métodos
        this.onProductLinkOpen = this.onProductLinkOpen.bind(this);
        this.onProductUnlink = this.onProductUnlink.bind(this);
        this.onProductSelect = this.onProductSelect.bind(this);
        this.onSearchInput = this.onSearchInput.bind(this);

        // Debounced search
        this.debouncedSearch = this.debounce(
            () => this.performSearch(),
            this.searchDebounceMs
        );
    }

    /**
     * Conecta el controlador registrando eventos
     */
    connect() {
        // Registrar acciones en el EventDispatcher
        eventDispatcher.register('product:link:open', this.onProductLinkOpen);
        eventDispatcher.register('product:unlink', this.onProductUnlink);
        eventDispatcher.register('product:select', this.onProductSelect);

        // Registrar listener para el input de búsqueda
        if (this.searchView.dom.searchInput) {
            this.searchView.dom.searchInput.addEventListener('input', this.onSearchInput);
        }

        // Hidratar el store desde el DOM
        this.store.hydrateFromDOM();

        // Actualizar botones según estado inicial
        this.updateAllButtonStates();
    }

    /**
     * Maneja la apertura del modal de vinculación
     * @param {HTMLElement} el - Elemento que disparó el evento
     */
    onProductLinkOpen(el) {
        this.currentConceptoIndex = el.dataset.index;

        // Limpiar búsqueda anterior
        this.searchView.clearSearch();
        this.searchView.renderEmptyState();

        // Abrir modal
        this.searchView.openModal();

        eventManager.emit('product:link:modal:opened', {
            index: this.currentConceptoIndex
        });
    }

    /**
     * Maneja la desvinculación de un producto
     * @param {HTMLElement} el - Elemento que disparó el evento
     */
    onProductUnlink(el) {
        const index = el.dataset.index;

        // Actualizar store
        this.store.removeLink(index);

        // Actualizar UI
        this.linkView.clearReferenceCell(index);
        this.linkView.clearHiddenInput(index);
        this.linkView.updateButtonsState(index, false);
        this.linkView.showSuccessFeedback(index);

        eventManager.emit('product:unlinked', { index });
    }

    /**
     * Maneja la selección de un producto
     * @param {HTMLElement} el - Elemento que disparó el evento
     */
    onProductSelect(el) {
        const referencia = el.dataset.referencia;

        if (!this.currentConceptoIndex) {
            console.warn('No hay concepto seleccionado');
            return;
        }

        // Actualizar store
        this.store.setLink(this.currentConceptoIndex, referencia);

        // Actualizar UI
        this.linkView.updateReferenceCell(this.currentConceptoIndex, referencia);
        this.linkView.updateHiddenInput(this.currentConceptoIndex, referencia);
        this.linkView.updateButtonsState(this.currentConceptoIndex, true);
        this.linkView.showSuccessFeedback(this.currentConceptoIndex);

        // Cerrar modal
        this.searchView.closeModal();

        // Notificar
        this.linkView.showToast(
            `Producto ${referencia} vinculado correctamente`,
            'success'
        );

        eventManager.emit('product:linked', {
            index: this.currentConceptoIndex,
            referencia: referencia
        });

        // Limpiar índice actual
        this.currentConceptoIndex = null;
    }

    /**
     * Maneja el input de búsqueda
     * @param {Event} event - Evento de input
     */
    onSearchInput(event) {
        const query = event.target.value.trim();

        if (query.length < 2) {
            this.searchView.renderEmptyState();
            return;
        }

        // Cancelar búsqueda anterior y programar nueva
        this.service.abort();
        this.debouncedSearch();
    }

    /**
     * Realiza la búsqueda de productos
     */
    async performSearch() {
        const query = this.searchView.getSearchValue();

        if (query.length < 2) {
            this.searchView.renderEmptyState();
            return;
        }

        // Mostrar estado de carga
        this.searchView.renderLoadingState();

        try {
            const productos = await this.service.searchProducts(query);
            this.searchView.renderProductsTable(productos);

            eventManager.emit('products:searched', {
                query: query,
                count: productos.length
            });
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error al buscar productos:', error);
                this.searchView.renderErrorState();
            }
        }
    }

    /**
     * Actualiza el estado de todos los botones según el store
     */
    updateAllButtonStates() {
        const links = this.store.getAllLinks();
        links.forEach((referencia, index) => {
            this.linkView.updateButtonsState(index, true);
        });
    }

    /**
     * Debounce helper
     * @param {Function} fn - Función a ejecutar
     * @param {number} wait - Tiempo de espera en ms
     * @returns {Function}
     */
    debounce(fn, wait = 200) {
        let timeout = null;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    /**
     * Obtiene estadísticas de vinculación
     * @returns {Object}
     */
    getStats() {
        return this.store.getStats();
    }

    /**
     * Desconecta el controlador
     */
    disconnect() {
        // Remover listeners si es necesario
        if (this.searchView.dom.searchInput) {
            this.searchView.dom.searchInput.removeEventListener('input', this.onSearchInput);
        }

        // Cancelar búsquedas pendientes
        this.service.abort();
    }
}
