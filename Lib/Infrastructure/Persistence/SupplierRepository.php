<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Proveedor;

final class SupplierRepository
{
    public function findByRfc(string $rfc): ?Proveedor
    {
        $supplier = new Proveedor();
        if (!$supplier->loadFromCode('', [new DataBaseWhere('cifnif', $rfc)])) {
            return null;
        }

        return $supplier;
    }

    public function create(): Proveedor
    {
        return new Proveedor();
    }

    public function save(Proveedor $supplier): bool
    {
        return $supplier->save();
    }
}
