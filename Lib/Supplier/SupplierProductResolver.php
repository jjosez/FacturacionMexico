<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Variante;

class SupplierProductResolver
{
    /**
     * @return array{product: ?Producto, created: bool, linked: bool}
     */
    public function resolve(array $concepto, Proveedor $supplier, SupplierInvoiceImportOptions $options): array
    {
        $product = $this->loadManualProduct((string)($concepto['referencia'] ?? ''));
        if ($product !== null) {
            return ['product' => $product, 'created' => false, 'linked' => true];
        }

        if ($options->shouldAutoMatch()) {
            $product = $this->loadSupplierProduct(
                (string)($concepto['NoIdentificacion'] ?? ''),
                $supplier
            );
            if ($product !== null) {
                return ['product' => $product, 'created' => false, 'linked' => true];
            }
        }

        if ($options->shouldCreateProducts()) {
            return [
                'product' => $this->createProduct($concepto),
                'created' => true,
                'linked' => false,
            ];
        }

        return ['product' => null, 'created' => false, 'linked' => false];
    }

    private function loadManualProduct(string $reference): ?Producto
    {
        if ($reference === '') {
            return null;
        }

        $variant = new Variante();
        if (!$variant->loadWhereEq('referencia', $reference)) {
            return null;
        }

        $product = $variant->getProducto();
        return $product instanceof Producto ? $product : null;
    }

    private function loadSupplierProduct(string $supplierReference, Proveedor $supplier): ?Producto
    {
        if ($supplierReference === '') {
            return null;
        }

        $link = new ProductoProveedor();
        $where = [
            Where::eq('refproveedor', $supplierReference),
            Where::eq('codproveedor', $supplier->codproveedor),
        ];
        if (!$link->loadWhere($where)) {
            return null;
        }

        $product = $link->getProducto();
        return $product instanceof Producto ? $product : null;
    }

    private function createProduct(array $concepto): Producto
    {
        $product = new Producto();
        $product->descripcion = $concepto['Descripcion'] ?? '';
        $product->referencia = $this->generateUniqueReference(
            !empty($concepto['NoIdentificacion'])
                ? $concepto['NoIdentificacion']
                : substr($product->descripcion, 0, 20)
        );

        if (!empty($concepto['ClaveProdServ'])) {
            $product->clave_sat = $concepto['ClaveProdServ'];
        }

        $product->tipoventa = 'unidad';
        $product->setPrice((float)($concepto['ValorUnitario'] ?? 0));

        if (!$product->save()) {
            throw new Exception('Error al crear el producto: ' . $product->descripcion);
        }

        return $product;
    }

    private function generateUniqueReference(string $base): string
    {
        $reference = substr(preg_replace('/[^a-zA-Z0-9]/', '', $base), 0, 20);
        $product = new Producto();

        if (!$product->load($reference)) {
            return $reference;
        }

        $counter = 1;
        do {
            $newReference = $reference . '_' . $counter;
            if (!$product->load($newReference)) {
                return $newReference;
            }
            $counter++;
        } while ($counter < 100);

        return $reference . '_' . time();
    }
}
