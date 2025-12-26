export class RelatedService {
  constructor({ endpoint = 'EditCfdiCliente', action = 'search-related-cfdis' } = {}) {
    this.endpoint = endpoint;
    this.action = action;
    this.abortController = null;
  }

  abort() {
    if (this.abortController) this.abortController.abort();
    this.abortController = null;
  }

  async searchCustomerCfdis({ customerCode, type, from, to }) {
    this.abort();
    this.abortController = new AbortController();

    const formData = new FormData();
    formData.append('action', this.action);
    formData.append('codcliente', customerCode ?? '');
    formData.append('tipo', type ?? '');
    formData.append('desde', from ?? '');
    formData.append('hasta', to ?? '');

    const response = await fetch(this.endpoint, {
      method: 'POST',
      body: formData,
      signal: this.abortController.signal,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    const data = await response.json();
    return Array.isArray(data) ? data : [];
  }
}
