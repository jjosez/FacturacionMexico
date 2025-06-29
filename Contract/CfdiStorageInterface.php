<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Contract;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;

interface CfdiStorageInterface
{
    public function save(FacturaCliente $invoice, string $xmlContent): ?CfdiCliente;
    public function saveXml(CfdiCliente $cfdi, string $xmlContent): bool;
    public function updateMailDate(CfdiCliente $cfdi): bool;
    public function updateStatus(CfdiCliente $cfdi, string $status): bool;
    public function deleteCfdi(CfdiCliente $cfdi): bool;
}
