<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Producto;

final class SupplierProductSearchRepository
{
    private DataBase $database;

    public function __construct(?DataBase $database = null)
    {
        $this->database = $database ?? new DataBase();
    }

    public function searchWithSupplierPriority(string $query, string $supplierCode): array
    {
        $sql = "SELECT
                    p.referencia,
                    p.descripcion,
                    p.tipoventa,
                    p.codfamilia,
                    p.preciocoste,
                    p.pvp,
                    p.stockfis,
                    p.controlstock,
                    p.referencia_fabricante,
                    pp.refproveedor,
                    pp.precio AS precio_proveedor,
                    CASE WHEN pp.refproveedor IS NOT NULL THEN 1 ELSE 0 END AS is_linked
                FROM productos p
                LEFT JOIN productos_proveedores pp ON p.referencia = pp.referencia AND pp.codproveedor = ?
                WHERE p.referencia LIKE ?
                   OR p.descripcion LIKE ?
                   OR p.referencia_fabricante LIKE ?
                   OR pp.refproveedor LIKE ?
                ORDER BY is_linked DESC, p.descripcion ASC
                LIMIT 50";

        $likeQuery = '%' . $this->database->escapeString($query) . '%';
        return $this->database->select($sql, [$supplierCode, $likeQuery, $likeQuery, $likeQuery, $likeQuery]);
    }

    public function searchStandard(string $query, bool $includeManufacturer): array
    {
        $where = [
            Where::orLike('referencia', $query),
            Where::orLike('descripcion', $query),
        ];

        if ($includeManufacturer) {
            array_unshift($where, Where::orLike('referencia_fabricante', $query));
        }

        return Producto::all($where, [], 0, 50);
    }
}
