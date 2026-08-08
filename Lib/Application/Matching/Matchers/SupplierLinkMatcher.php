<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\Matchers;

use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatcherInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatchResult;

class SupplierLinkMatcher implements MatcherInterface
{
    public function getName(): string
    {
        return 'supplier_link';
    }

    public function getPriority(): int
    {
        return 2;
    }

    public function match(array $concepto, Proveedor $supplier): ?MatchResult
    {
        $noIdentificacion = $concepto['NoIdentificacion'] ?? '';

        if (empty($noIdentificacion)) {
            return null;
        }

        $productSupplier = new ProductoProveedor();
        $where = [
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('refproveedor', $noIdentificacion),
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('codproveedor', $supplier->codproveedor)
        ];

        if (false === $productSupplier->loadWhere($where)) {
            return null;
        }

        $product = new Producto();
        if (false === $product->load($productSupplier->referencia)) {
            return null;
        }

        return MatchResult::exactMatch($product, $this->getName(), true);
    }
}
