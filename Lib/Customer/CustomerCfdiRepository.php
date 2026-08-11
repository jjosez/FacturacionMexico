<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Customer;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiParsedData;

final class CustomerCfdiRepository
{
    public function createFromInvoice(FacturaCliente $invoice, CfdiParsedData $data): ?CfdiCliente
    {
        $cfdi = new CfdiCliente();
        $cfdi->codcliente = $invoice->codcliente;
        $cfdi->idfactura = $invoice->idfactura;
        $cfdi->cfdiglobal = $invoice->isGlobalInvoice() ?: null;
        $cfdi->coddivisa = $data->currency;
        $cfdi->estado = 'Timbrado';
        $cfdi->fecha_emision = $data->issueDate;
        $cfdi->fecha_timbrado = $data->stampedAt;
        $cfdi->folio = $data->folio;
        $cfdi->forma_pago = $data->paymentForm;
        $cfdi->metodo_pago = $data->paymentMethod;
        $cfdi->receptor_nombre = $data->recipientName;
        $cfdi->receptor_rfc = $data->recipientRfc;
        $cfdi->serie = $data->series;
        $cfdi->tipo = $data->type;
        $cfdi->total = $data->total;
        $cfdi->uuid = $data->uuid;
        $cfdi->version = $data->version;

        return $cfdi->save() ? $cfdi : null;
    }

    public function save(CfdiCliente $cfdi): bool
    {
        return $cfdi->save();
    }

    public function updateStatus(CfdiCliente $cfdi, CfdiStatus $status): bool
    {
        $cfdi->estado = $status->value;
        return $cfdi->save();
    }

    public function updateMailDate(CfdiCliente $cfdi): bool
    {
        $cfdi->updateMailDate();
        return $cfdi->save();
    }
}
