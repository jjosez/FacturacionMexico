<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\Matchers;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatcherInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatchResult;

class ReferenceMatcher implements MatcherInterface
{
    public function getName(): string
    {
        return 'reference';
    }

    public function getPriority(): int
    {
        return 1;
    }

    public function match(array $concepto, Proveedor $supplier): ?MatchResult
    {
        $noIdentificacion = $concepto['NoIdentificacion'] ?? '';

        if (empty($noIdentificacion)) {
            return null;
        }

        $product = new Producto();

        if (Plugins::isEnabled('SKU')) {
            $where = [
                new DataBaseWhere('referencia_fabricante', $noIdentificacion)
            ];

            if ($product->loadWhere($where)) {
                return $this->createMatch($product, $supplier, 'sku_reference');
            }
        }

        $where = [
            new DataBaseWhere('referencia', $noIdentificacion)
        ];

        if ($product->loadWhere($where)) {
            return $this->createMatch($product, $supplier, 'reference');
        }

        return null;
    }

    private function createMatch(Producto $product, Proveedor $supplier, string $method): MatchResult
    {
        $isLinked = $this->isLinkedToSupplier($product->referencia, $supplier->codproveedor);

        return MatchResult::exactMatch($product, $method, $isLinked);
    }

    private function isLinkedToSupplier(string $referencia, string $codproveedor): bool
    {
        $sql = "SELECT 1 FROM productos_proveedores WHERE referencia = ? AND codproveedor = ? LIMIT 1";

        $result = static function () use ($sql, $referencia, $codproveedor) {
            $db = new \FacturaScripts\Core\Base\DataBase();
            return $db->select($sql, [$referencia, $codproveedor]);
        };

        $result = $result();

        return !empty($result);
    }
}
