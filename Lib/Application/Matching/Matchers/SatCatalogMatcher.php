<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\Matchers;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatcherInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatchResult;

class SatCatalogMatcher implements MatcherInterface
{
    private const MIN_CONFIDENCE = 0.80;

    public function getName(): string
    {
        return 'sat_catalog';
    }

    public function getPriority(): int
    {
        return 3;
    }

    public function match(array $concepto, Proveedor $supplier): ?MatchResult
    {
        $claveProdServ = $concepto['ClaveProdServ'] ?? '';

        if (empty($claveProdServ)) {
            return null;
        }

        $product = $this->findProductBySatCode($claveProdServ);

        if ($product === null) {
            return null;
        }

        $isLinked = $this->isLinkedToSupplier($product->referencia, $supplier->codproveedor);

        if ($isLinked) {
            return MatchResult::exactMatch($product, $this->getName(), true);
        }

        return MatchResult::highConfidence(
            $product,
            $this->getName(),
            'Coincidencia por clave SAT'
        );
    }

    private function findProductBySatCode(string $claveProdServ): ?Producto
    {
        return null;
    }

    private function isLinkedToSupplier(string $referencia, string $codproveedor): bool
    {
        $sql = "SELECT 1 FROM productos_proveedores WHERE referencia = ? AND codproveedor = ? LIMIT 1";

        $db = new \FacturaScripts\Core\Base\DataBase();
        $result = $db->select($sql, [$referencia, $codproveedor]);

        return !empty($result);
    }
}
