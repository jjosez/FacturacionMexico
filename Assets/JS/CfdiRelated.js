export class CfdiRelated {
    constructor() {
        this.tablaCfdiModal = document.getElementById('tablaCfdiModal');
        this.tablaRelacionados = document.getElementById('tablaRelacionados');
        this.confirmRelacionados = document.getElementById('confirmRelacionados');
        this.tipoRelacionModal = document.getElementById('tipoRelacionModal');
    }

    init() {
        const tipo = document.getElementById('filterTipoModal');
        if (tipo) {
            tipo.addEventListener('change', () => this.fetchCfdisCliente());
        }

        const desde = document.getElementById('filterFechaDesdeModal');
        if (desde) {
            desde.addEventListener('change', () => this.fetchCfdisCliente());
        }

        const hasta = document.getElementById('filterFechaHastaModal');
        if (hasta) {
            hasta.addEventListener('change', () => this.fetchCfdisCliente());
        }

        this.confirmRelacionados.addEventListener('click', () => this.addSelectedCfdis());
        //document.getElementById('addCfdiRelacionBtn').addEventListener('click', () => this.addCfdiFromUUID());

        this.tablaRelacionados.addEventListener('click', e => {
            if (e.target.closest('.btn-remove')) {
                e.target.closest('tr').remove();
            }
        });

        this.fetchCfdisCliente();
    }

    async fetchCfdisCliente() {
        const formData = new FormData();
        formData.append('action', 'search-related-cfdis');
        formData.append('codcliente', document.getElementById('codcliente').value);
        formData.append('tipo', document.getElementById('filterTipoModal').value);
        formData.append('desde', document.getElementById('filterFechaDesdeModal').value);
        formData.append('hasta', document.getElementById('filterFechaHastaModal').value);

        this.tablaCfdiModal.innerHTML = `<tr><td colspan="5" class="text-center">Cargando...</td></tr>`;

        try {
            const response = await fetch('EditCfdiCliente', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            this.renderModalTable(data);
        } catch {
            this.tablaCfdiModal.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Error al cargar datos</td></tr>`;
        }
    }

    renderModalTable(cfdis) {
        this.tablaCfdiModal.innerHTML = '';
        if (!cfdis.length) {
            this.tablaCfdiModal.innerHTML = `<tr><td colspan="5" class="text-center">Sin resultados</td></tr>`;
            return;
        }

        cfdis.slice(0, 5).forEach(c => {
            const row = document.createElement('tr');
            row.innerHTML = this.cfdiModalRowTemplate(c);
            this.tablaCfdiModal.appendChild(row);
        });
    }

    cfdiModalRowTemplate(c) {
        return `
            <td><input type="checkbox"
                       value="${c.uuid}"
                       data-tipo="${c.tipo}"
                       data-total="${c.total}"
                       data-fecha="${c.fecha_timbrado}"
                       data-global="${c.cfdiglobal}"
                       data-estado="${c.estado}"
                       data-receptor="${c.receptor_nombre}"
                       ${c.estado === 'cancelado' ? 'disabled' : ''}></td>
            <td>${c.receptor_nombre}</td>
            <td>${c.tipo}</td>
            <td>${c.uuid} ${this.buildEstadoBadge(c.estado)} ${this.buildGlobalBadge(c.cfdiglobal)}</td>
            <td>${c.total}</td>
            <td>${c.fecha_timbrado}</td>`;
    }

    addSelectedCfdis() {
        const seleccionados = this.tablaCfdiModal.querySelectorAll('input[type="checkbox"]:checked');
        const tipoRelacion = this.tipoRelacionModal.value;

        if (!tipoRelacion) {
            alert('Por favor seleccione un tipo de relación');
            return;
        }

        if (seleccionados.length === 0) {
            alert('Por favor seleccione al menos un CFDI');
            return;
        }

        seleccionados.forEach(cb => {
            if (!this.isUuidInRelacionados(cb.value)) {
                const row = document.createElement('tr');
                row.innerHTML = this.relatedRowTemplate(cb, tipoRelacion);
                this.tablaRelacionados.appendChild(row);
                cb.checked = false; // Desmarcar después de agregar
            }
        });

        // Cerrar el collapse usando Bootstrap 5
        const collapseElement = document.getElementById('collapseBusqueda');
        const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, {toggle: false});
        bsCollapse.hide();
    }

    relatedRowTemplate(cb, tipoRelacion) {
        return `
            <td>${cb.dataset.receptor}</td>
            <td>${tipoRelacion}</td>
            <td>${cb.value} ${this.buildEstadoBadge(cb.dataset.estado)} ${this.buildGlobalBadge(cb.dataset.global)}
                <input type="hidden" name="relacionados[${tipoRelacion}][]" value="${cb.value}">
            </td>
            <td>${cb.dataset.total}</td>
            <td>${cb.dataset.fecha}</td>
            <td><button type="button" class="btn btn-sm btn-danger btn-remove">Eliminar</button></td>`;
    }

    isUuidInRelacionados(uuid) {
        return Array.from(this.tablaRelacionados.querySelectorAll('td'))
            .some(td => td.textContent.includes(uuid));
    }

    async addCfdiFromUUID() {
        const codcliente = document.getElementById('codcliente').value;
        const uuid = document.getElementById('uuidrelacionado').value;

        const formData = new URLSearchParams({
            action: 'cfdi-relacionado',
            codcliente,
            uuid
        });

        try {
            const response = await fetch('EditCfdiCliente', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData.toString()
            });
            const result = await response.json();
            this.addRelatedCfdi(result);
        } catch (err) {
            alert('ERROR al agregar CFDI: ' + err);
        }
    }

    addRelatedCfdi(result) {
        const tipoRelacion = document.getElementById('tiporelacion').value;
        const tipoRelacionText = document.getElementById('tiporelacion').selectedOptions[0].text;

        if (!this.isUuidInRelacionados(result.uuid)) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${tipoRelacionText}
                    <input type="hidden" name="relacionados[${tipoRelacion}][]" value="${result.uuid}">
                </td>
                <td>${result.uuid}</td>
                <td>${result.total}</td>
                <td>${result.fecha}</td>
                <td><button type="button" class="btn btn-sm btn-danger btn-remove">Eliminar</button></td>`;
            this.tablaRelacionados.appendChild(row);
        }
    }

    buildGlobalBadge(cfdiglobal) {
        return (cfdiglobal === '1' || cfdiglobal === 1)
            ? `<span class="badge badge-info ml-1">Global</span>` : '';
    }

    buildEstadoBadge(estado) {
        let badgeClass = 'badge-secondary';
        let label = estado || 'Desconocido';
        switch (label.toLowerCase()) {
            case 'timbrado':
                badgeClass = 'badge-success';
                label = 'Vigente';
                break;
            case 'cancelado':
                badgeClass = 'badge-danger';
                label = 'Cancelado';
                break;
            case 'pendiente':
                badgeClass = 'badge-warning';
                label = 'Pendiente';
                break;
        }
        return `<span class="badge ${badgeClass} ml-1">${label}</span>`;
    }
}
