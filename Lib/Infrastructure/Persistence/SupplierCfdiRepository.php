<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Dinamic\Model\CfdiProveedor;

final class SupplierCfdiRepository
{
    public function existsByUuid(string $uuid): bool
    {
        $cfdi = new CfdiProveedor();
        return $cfdi->loadFromUuid($uuid);
    }

    public function create(): CfdiProveedor
    {
        return new CfdiProveedor();
    }

    public function save(CfdiProveedor $cfdi): bool
    {
        return $cfdi->save();
    }
}
