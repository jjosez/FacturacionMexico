const relatedTableBody = document.getElementById('tablaRelacionados');

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
            alert('ERROR: ' + xhr.responseText)
        }
    });
}

function addRelatedCfdi(result) {
    const row = relatedTableBody.insertRow();

    const cellRazonSocial = row.insertCell(0);
    cellRazonSocial.setAttribute('class', 'align-middle');
    cellRazonSocial.innerHTML = result.razonreceptor;

    const cellFolioFiscal = row.insertCell(1);
    cellFolioFiscal.setAttribute('class', 'align-middle');
    cellFolioFiscal.innerHTML = result.uuid;

    const element = document.createElement('input');
    element.type = 'hidden';
    element.name = 'relacionados[]';
    element.value = result.uuid;
    cellFolioFiscal.appendChild(element);

    const cellTotal = row.insertCell(2);
    cellTotal.setAttribute('class', 'align-middle');
    cellTotal.innerHTML = result.total;

    const cellFecha = row.insertCell(3);
    cellFecha.setAttribute('class', 'align-middle');
    cellFecha.innerHTML = result.fecha;

    const cellAction = row.insertCell(4)
    const button = document.createElement('button');
    button.setAttribute('type', 'button');
    button.className = 'btn btn-danger btn-remove';

    var buttonDeleteIcon = document.createElement('i');
    buttonDeleteIcon.setAttribute('class', 'fas fw fa-trash');
    button.appendChild(buttonDeleteIcon);

    cellAction.appendChild(button);
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

    $("#tablaRelacionados").on("click", ".btn-remove", function () {
        $(this).closest('tr').remove();
    });
})
