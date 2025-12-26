import { RelatedService } from '../services/RelatedService.js';
import { RelatedView } from '../views/RelatedView.js';
import { RelatedStore } from '../models/RelatedStore.js';

export class RelatedController {
  constructor(options = {}) {
    this.service = options.service ?? new RelatedService(options);
    this.view = options.view ?? new RelatedView(options);
    this.store = options.store ?? new RelatedStore();

    this.state = { currentCfdi: null };

    this.onFilterChange = this.onFilterChange.bind(this);
    this.onSearchClick = this.onSearchClick.bind(this);
    this.onRelatedClick = this.onRelatedClick.bind(this);
    this.onConfirm = this.onConfirm.bind(this);

    this.debouncedFetch = this.debounce(() => this.fetchAndRender(), options.fetchDebounceMs ?? 250);
  }

  connect() {
    this.view.connect();
    if (!this.view.dom.searchTableBody || !this.view.dom.relatedTableBody) return;

    // events
    this.view.dom.filterType?.addEventListener('change', this.onFilterChange);
    this.view.dom.filterFrom?.addEventListener('change', this.onFilterChange);
    this.view.dom.filterTo?.addEventListener('change', this.onFilterChange);

    this.view.dom.searchTableBody.addEventListener('click', this.onSearchClick);
    this.view.dom.relatedTableBody.addEventListener('click', this.onRelatedClick);
    this.view.dom.confirmButton?.addEventListener('click', this.onConfirm);

    // ✅ hydrate store once from existing related rows
    const preloaded = this.view.extractPreloadedUuids();
    this.store.hydrateFromUuids(preloaded);

    this.fetchAndRender();
  }

  async fetchAndRender() {
    const filters = this.view.getFilters();
    this.view.renderSearchState('loading');

    try {
      const cfdis = await this.service.searchCustomerCfdis(filters);
      this.view.renderSearchTable(cfdis);
    } catch (err) {
      if (err?.name === 'AbortError') return;
      this.view.renderSearchState('error');
    }
  }

  onFilterChange() {
    this.debouncedFetch();
  }

  onSearchClick(event) {
    const btn = event.target.closest('.btn-add-cfdi');
    if (!btn) return;

    // dataset strings
    this.state.currentCfdi = btn.dataset;

    this.view.openRelationModal({
      uuid: btn.dataset.uuid,
      total: btn.dataset.total,
    });
  }

  onConfirm() {
    const relationType = this.view.getSelectedRelationType();
    if (!relationType) {
      this.view.showToast('Selecciona un tipo de relación', 'warning', 'Falta información');
      return;
    }

    const cfdi = this.state.currentCfdi;
    if (!cfdi?.uuid) {
      this.view.showToast('No hay CFDI seleccionado', 'danger', 'Error');
      return;
    }

    // ✅ O(1) duplicate check
    if (this.store.hasUuid(cfdi.uuid)) {
      this.view.showToast('Este UUID ya está agregado en relacionados', 'warning', 'Duplicado');
      return;
    }

    const templateData = {
      receptor: cfdi.receptor,
      tipoRelacion: relationType,
      uuid: cfdi.uuid,
      estado: cfdi.estado,
      global: cfdi.global,
      total: cfdi.total,
      fecha: cfdi.fecha,
    };

    this.view.appendRelatedRow(templateData, cfdi.uuid);
    this.store.addUuid(cfdi.uuid);

    this.view.showToast('CFDI agregado a relacionados', 'success', 'OK');
    this.view.closeRelationModal();
    this.state.currentCfdi = null;
  }

  onRelatedClick(event) {
    const btn = event.target.closest('.btn-remove');
    if (!btn) return;

    const uuid = this.view.removeRelatedRowByButton(btn);
    if (uuid) this.store.removeUuid(uuid);

    this.view.showToast('Relacionado eliminado', 'info', 'OK');
  }

  debounce(fn, wait = 200) {
    let t = null;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }
}
