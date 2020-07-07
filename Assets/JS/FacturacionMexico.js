const relatedTable = document.getElementById('tablaRelacionados');

function addCfdiFromUUID() {
    var codcliente = document.getElementById('codcliente').value;
    var uuid = document.getElementById('uuidrelacionado').value;

    var data = {
        action: 'cfdi-relacionado',
        codcliente: codcliente,
        uuid: uuid
    }

    $.ajax({
        type: "POST",
        url: 'EditCfdiCliente',
        dataType: "json",
        data: data,
        success: function (results) {
            addRelatedCfdi(results);
            console.log(results)
        },
        error: function (xhr, status, error) {
            console.log('ERROR:', xhr.responseText)
        }
    });
}

function addRelatedCfdi(result) {
    var row = relatedTable.insertRow();

    var cellRazonSocial = row.insertCell(0);
    cellRazonSocial.innerHTML = result.razonreceptor;

    var cellFolioFiscal = row.insertCell(1);
    cellFolioFiscal.innerHTML = result.uuid;

    var element = document.createElement('input');
    element.type = 'hidden';
    element.name = 'relacionados[]';
    element.value = result.uuid;
    cellFolioFiscal.appendChild(element);

    var cellTotal = row.insertCell(2);
    cellTotal.innerHTML = result.total;

    var cellAction = row.insertCell(3);
    var button = document.createElement('input');
    button.setAttribute('type', 'button');
    button.setAttribute('value', 'X');
    button.setAttribute('onclick', 'removeRelatedCfdi(this)');
    button.className = 'btn btn-danger';

    cellAction.appendChild(button);
}

function removeRelatedCfdi(button) {
    var row = button.parentNode.parentNode;

    relatedTable.deleteRow(row.rowIndex);
}

$(document).ready(function () {
    $('form').submit(function (e) {
        var currentForm = this;
        var message = 'Esta acción necesita confirmación. ¿Está seguro de que desea continuar?';

        e.preventDefault();
        bootbox.confirm({
            title: 'Confirmar',
            message: message,
            closeButton: false,
            buttons: {
                cancel: {
                    label: '<i class="fas fa-times"></i> Cancelar'
                },
                confirm: {
                    label: '<i class="fas fa-check"></i> Continuar',
                    className: "btn-warning"
                }
            },
            callback: function (result) {
                if (result) {
                    currentForm.submit();
                }
            }
        });
    });

    $('#addCfdiRelacionBtn').click(function () {
        addCfdiFromUUID();
    });
})