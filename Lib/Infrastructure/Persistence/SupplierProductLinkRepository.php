<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Variante;

final class SupplierProductLinkRepository
{
    public function findProductIdByReference(string $reference): ?int
    {
        $variant = new Variante();
        if (!$variant->loadWhereEq('referencia', $reference)) {
            return null;
        }

        return (int)$variant->idproducto;
    }

    public function find(string $reference, string $supplierCode): ?ProductoProveedor
    {
        $link = new ProductoProveedor();
        if (!$link->loadWhere([
            Where::eq('referencia', $reference),
            Where::eq('codproveedor', $supplierCode),
        ])) {
            return null;
        }

        return $link;
    }

    public function create(): ProductoProveedor
    {
        return new ProductoProveedor();
    }

    /** @return ProductoProveedor[] */
    public function findBySupplier(string $supplierCode): array
    {
        return ProductoProveedor::all([Where::eq('codproveedor', $supplierCode)]);
    }

    /** @return ProductoProveedor[] */
    public function findByProduct(string $reference): array
    {
        return ProductoProveedor::all([Where::eq('referencia', $reference)]);
    }

    public function save(ProductoProveedor $link): bool
    {
        return $link->save();
    }

    public function delete(ProductoProveedor $link): bool
    {
        return $link->delete();
    }
}
