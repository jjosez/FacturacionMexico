<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Core\Where;

final class SupplierProductRepository
{
    public function findByVariantReference(string $reference): ?Producto
    {
        $variant = new Variante();
        if (!$variant->loadWhereEq('referencia', $reference)) {
            return null;
        }

        $product = $variant->getProducto();
        return $product instanceof Producto ? $product : null;
    }

    public function findBySupplierReference(string $reference, Proveedor $supplier): ?Producto
    {
        $link = new ProductoProveedor();
        if (!$link->loadWhere([
            Where::eq('refproveedor', $reference),
            Where::eq('codproveedor', $supplier->codproveedor),
        ])) {
            return null;
        }

        $product = $link->getProducto();
        return $product instanceof Producto ? $product : null;
    }

    public function create(): Producto
    {
        return new Producto();
    }

    public function exists(string $reference): bool
    {
        $product = new Producto();
        return $product->load($reference);
    }

    public function save(Producto $product): void
    {
        if (!$product->save()) {
            throw new \Exception('Error al crear el producto: ' . $product->descripcion);
        }
    }
}
