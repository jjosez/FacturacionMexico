import cfdiTemplates from './TemplateManager.js';

export class RelatedView {
  constructor({
    templates = cfdiTemplates,
    ids = {},
    toastDelayMs = 3500,
    searchColspan = 7,
  } = {}) {
    this.templates = templates;
    this.toastDelayMs = toastDelayMs;
    this.searchColspan = searchColspan;

    this.ids = {
      searchTableBody: 'cfdi:search:list:view',
      relatedTableBody: 'cfdi:related:list:view',

      confirmButton: 'confirmRelacionados',
      relationTypeSelect: 'tipoRelacionModal',
      relationModal: 'modalTipoRelacion',
      modalUuid: 'modalCfdiUuid',
      modalTotal: 'modalCfdiTotal',

      customerCode: 'codcliente',
      filterType: 'filterTipoModal',
      filterFrom: 'filterFechaDesdeModal',
      filterTo: 'filterFechaHastaModal',

      toast: 'cfdiToast',
      toastTitle: 'cfdiToastTitle',
      toastBody: 'cfdiToastBody',
      toastTime: 'cfdiToastTime',

      ...ids,
    };

    this.dom = {};
    this.modal = null;
    this.toast = null;
  }

  connect() {
    this.cacheDom();
    this.initToast();
    this.initModal();
  }

  cacheDom() {
    this.dom = {
      searchTableBody: document.getElementById(this.ids.searchTableBody),
      relatedTableBody: document.getElementById(this.ids.relatedTableBody),

      confirmButton: document.getElementById(this.ids.confirmButton),
      relationTypeSelect: document.getElementById(this.ids.relationTypeSelect),
      relationModalEl: document.getElementById(this.ids.relationModal),

      modalUuid: document.getElementById(this.ids.modalUuid),
      modalTotal: document.getElementById(this.ids.modalTotal),

      customerCode: document.getElementById(this.ids.customerCode),
      filterType: document.getElementById(this.ids.filterType),
      filterFrom: document.getElementById(this.ids.filterFrom),
      filterTo: document.getElementById(this.ids.filterTo),

      toastEl: document.getElementById(this.ids.toast),
      toastTitle: document.getElementById(this.ids.toastTitle),
      toastBody: document.getElementById(this.ids.toastBody),
      toastTime: document.getElementById(this.ids.toastTime),
    };
  }

  initToast() {
    if (!this.dom.toastEl) return;
    this.toast = bootstrap.Toast.getOrCreateInstance(this.dom.toastEl, { delay: this.toastDelayMs });
  }

  showToast(message, type = 'info', title = 'Aviso') {
    if (!this.toast || !this.dom.toastEl) {
      console.log(`[${type}] ${title}: ${message}`);
      return;
    }

    const header = this.dom.toastEl.querySelector('.toast-header');
    header?.classList.remove('text-bg-success', 'text-bg-warning', 'text-bg-danger', 'text-bg-info');

    const cls = {
      success: 'text-bg-success',
      warning: 'text-bg-warning',
      danger: 'text-bg-danger',
      info: 'text-bg-info',
    }[type] ?? 'text-bg-info';

    header?.classList.add(cls);

    this.dom.toastTitle && (this.dom.toastTitle.textContent = title);
    this.dom.toastBody && (this.dom.toastBody.textContent = message);
    this.dom.toastTime && (this.dom.toastTime.textContent = new Date().toLocaleTimeString());

    this.toast.show();
  }

  initModal() {
    if (!this.dom.relationModalEl) return;
    this.modal = bootstrap.Modal.getOrCreateInstance(this.dom.relationModalEl);
  }

  openRelationModal({ uuid = '', total = '' } = {}) {
    this.dom.modalUuid && (this.dom.modalUuid.textContent = uuid);
    this.dom.modalTotal && (this.dom.modalTotal.textContent = total);
    this.modal?.show();
  }

  closeRelationModal() {
    this.modal?.hide();
    this.dom.relationTypeSelect && (this.dom.relationTypeSelect.value = '');
  }

  getFilters() {
    return {
      customerCode: this.dom.customerCode?.value ?? '',
      type: this.dom.filterType?.value ?? '',
      from: this.dom.filterFrom?.value ?? '',
      to: this.dom.filterTo?.value ?? '',
    };
  }

  getSelectedRelationType() {
    return this.dom.relationTypeSelect?.value ?? '';
  }

  renderSearchState(state) {
    const c = this.searchColspan;
    const map = {
      loading: `<tr><td colspan="${c}" class="text-center">Cargando...</td></tr>`,
      empty: `<tr><td colspan="${c}" class="text-center">Sin resultados</td></tr>`,
      error: `<tr><td colspan="${c}" class="text-center text-danger">Error al cargar datos</td></tr>`,
    };
    this.dom.searchTableBody.innerHTML = map[state] ?? '';
  }

  renderSearchTable(cfdis) {
    this.dom.searchTableBody.innerHTML = '';

    if (!cfdis.length) {
      this.renderSearchState('empty');
      return;
    }

    const frag = document.createDocumentFragment();
    for (const cfdi of cfdis) {
      const tr = document.createElement('tr');
      tr.innerHTML = this.templates.renderToString('cfdi:search:row:template', cfdi);
      frag.appendChild(tr);
    }
    this.dom.searchTableBody.appendChild(frag);
  }

  appendRelatedRow(templateData, uuid) {
    const tr = document.createElement('tr');
    tr.dataset.uuid = uuid;
    tr.innerHTML = this.templates.renderToString('cfdi:related:row:template', templateData);
    this.dom.relatedTableBody.appendChild(tr);
    return tr;
  }

  removeRelatedRowByButton(buttonEl) {
    const tr = buttonEl.closest('tr');
    const uuid = tr?.dataset?.uuid || this.extractUuidFromRow(tr);
    tr?.remove();
    return uuid; // para que el controller actualice el store
  }

  extractUuidFromRow(tr) {
    if (!tr) return '';
    const hidden = tr.querySelector('input[type="hidden"][name^="relacionados"]');
    return hidden?.value ?? '';
  }

  // ✅ usado solo una vez para hidratar store
  extractPreloadedUuids() {
    const uuids = [];
    this.dom.relatedTableBody.querySelectorAll('tr').forEach((tr) => {
      const uuid = tr.dataset.uuid || this.extractUuidFromRow(tr);
      if (uuid) {
        tr.dataset.uuid = uuid; // normaliza también DOM
        uuids.push(uuid);
      }
    });
    return uuids;
  }
}
