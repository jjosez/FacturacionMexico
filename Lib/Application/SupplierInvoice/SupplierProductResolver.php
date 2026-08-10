<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice;

use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierProductRepository;

class SupplierProductResolver
{
    private SupplierProductRepository $productRepository;

    public function __construct(?SupplierProductRepository $productRepository = null)
    {
        $this->productRepository = $productRepository ?? new SupplierProductRepository();
    }

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

        return $this->productRepository->findByVariantReference($reference);
    }

    private function loadSupplierProduct(string $supplierReference, Proveedor $supplier): ?Producto
    {
        if ($supplierReference === '') {
            return null;
        }

        return $this->productRepository->findBySupplierReference($supplierReference, $supplier);
    }

    private function createProduct(array $concepto): Producto
    {
        $product = $this->productRepository->create();
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

        $this->productRepository->save($product);

        return $product;
    }

    private function generateUniqueReference(string $base): string
    {
        $reference = substr(preg_replace('/[^a-zA-Z0-9]/', '', $base), 0, 20);
        if (!$this->productRepository->exists($reference)) {
            return $reference;
        }

        $counter = 1;
        do {
            $newReference = $reference . '_' . $counter;
            if (!$this->productRepository->exists($newReference)) {
                return $newReference;
            }
            $counter++;
        } while ($counter < 100);

        return $reference . '_' . time();
    }
}
