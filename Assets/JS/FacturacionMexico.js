function getCfdiFromUUID() {
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
           console.log(results)
        },
        error: function (xhr, status, error) {
            console.log('Error:', xhr.responseText)
        }
    });
}

function addCfdiRelacionado(result) {
    var row = '<tr>'
        + '';

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

    $('#getCfdiRelacionBtn').click(function () {
        getCfdiFromUUID();
    });
})