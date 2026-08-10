<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Dinamic\Model\FacturaCliente;

final class CustomerInvoiceRepository
{
    public function save(FacturaCliente $invoice): bool
    {
        return $invoice->save();
    }
}
