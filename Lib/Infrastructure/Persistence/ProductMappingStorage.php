<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Proveedor;

class ProductMappingStorage
{
    public const TABLE = 'cfdi_product_mappings';

    private array $memoryCache = [];

    public function __construct()
    {
    }

    public function getMapping(int $companyId, string $emisorRfc, string $noIdentificacion): ?ProductMapping
    {
        $cacheKey = $this->buildCacheKey($companyId, $emisorRfc, $noIdentificacion);

        if (isset($this->memoryCache[$cacheKey])) {
            return $this->memoryCache[$cacheKey];
        }

        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE idempresa = ? AND emisor_rfc = ? AND cfdi_referencia = ?"
            . " LIMIT 1";

        $db = new DataBase();
        $result = $db->select($sql, [$companyId, $emisorRfc, $noIdentificacion]);

        if (empty($result)) {
            return null;
        }

        $mapping = $this->hydrateFromRow($result[0]);
        $this->memoryCache[$cacheKey] = $mapping;

        return $mapping;
    }

    public function getMappingBySupplierRef(string $codproveedor, string $cfdiReferencia): ?ProductMapping
    {
        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE codproveedor = ? AND cfdi_referencia = ?"
            . " ORDER BY updated_at DESC LIMIT 1";

        $db = new DataBase();
        $result = $db->select($sql, [$codproveedor, $cfdiReferencia]);

        if (empty($result)) {
            return null;
        }

        return $this->hydrateFromRow($result[0]);
    }

    public function saveMapping(ProductMapping $mapping): bool
    {
        $db = new DataBase();

        $existing = $this->getMapping(
            $mapping->idempresa,
            $mapping->emisor_rfc,
            $mapping->cfdi_referencia
        );

        if ($existing !== null) {
            $mapping->id = $existing->id;
            $mapping->updated_at = date('Y-m-d H:i:s');

            $sql = "UPDATE " . self::TABLE . " SET "
                . " codproveedor = ?, referencia = ?, match_method = ?, confidence = ?, "
                . " updated_at = ?"
                . " WHERE id = ?";

            $result = $db->exec($sql, [
                $mapping->codproveedor,
                $mapping->referencia,
                $mapping->match_method,
                $mapping->confidence,
                $mapping->updated_at,
                $mapping->id
            ]);
        } else {
            $mapping->created_at = date('Y-m-d H:i:s');
            $mapping->updated_at = date('Y-m-d H:i:s');

            $sql = "INSERT INTO " . self::TABLE
                . " (codproveedor, idempresa, emisor_rfc, cfdi_referencia, referencia, "
                . " match_method, confidence, created_at, updated_at)"
                . " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $result = $db->exec($sql, [
                $mapping->codproveedor,
                $mapping->idempresa,
                $mapping->emisor_rfc,
                $mapping->cfdi_referencia,
                $mapping->referencia,
                $mapping->match_method,
                $mapping->confidence,
                $mapping->created_at,
                $mapping->updated_at
            ]);

            if ($result) {
                $mapping->id = $db->getLastIdentity();
            }
        }

        $cacheKey = $this->buildCacheKey($mapping->idempresa, $mapping->emisor_rfc, $mapping->cfdi_referencia);
        $this->memoryCache[$cacheKey] = $mapping;

        return $result;
    }

    public function deleteMapping(int $mappingId): bool
    {
        $sql = "DELETE FROM " . self::TABLE . " WHERE id = ?";

        $db = new DataBase();
        $result = $db->exec($sql, [$mappingId]);

        foreach ($this->memoryCache as $key => $mapping) {
            if ($mapping->id === $mappingId) {
                unset($this->memoryCache[$key]);
                break;
            }
        }

        return $result;
    }

    public function getMappingsByCompany(int $companyId, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE idempresa = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?";

        $db = new DataBase();
        $result = $db->select($sql, [$companyId, $limit, $offset]);

        return array_map(fn($row) => $this->hydrateFromRow($row), $result);
    }

    public function getMappingsBySupplier(string $codproveedor, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE codproveedor = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?";

        $db = new DataBase();
        $result = $db->select($sql, [$codproveedor, $limit, $offset]);

        return array_map(fn($row) => $this->hydrateFromRow($row), $result);
    }

    public function getMappingStats(int $companyId): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN match_method = 'exact' THEN 1 ELSE 0 END) as exact_matches,
                    SUM(CASE WHEN match_method = 'supplier_link' THEN 1 ELSE 0 END) as supplier_links,
                    SUM(CASE WHEN match_method = 'sat_catalog' THEN 1 ELSE 0 END) as sat_matches,
                    SUM(CASE WHEN match_method = 'fuzzy_description' THEN 1 ELSE 0 END) as fuzzy_matches,
                    AVG(confidence) as avg_confidence
                FROM " . self::TABLE . "
                WHERE idempresa = ?";

        $db = new DataBase();
        $result = $db->select($sql, [$companyId]);

        return $result[0] ?? [
            'total' => 0,
            'exact_matches' => 0,
            'supplier_links' => 0,
            'sat_matches' => 0,
            'fuzzy_matches' => 0,
            'avg_confidence' => 0
        ];
    }

    public function clearCache(): void
    {
        $this->memoryCache = [];
    }

    private function buildCacheKey(int $companyId, string $emisorRfc, string $cfdiReferencia): string
    {
        return "{$companyId}:{$emisorRfc}:{$cfdiReferencia}";
    }

    private function hydrateFromRow(array $row): ProductMapping
    {
        $mapping = new ProductMapping();
        $mapping->id = (int)$row['id'];
        $mapping->codproveedor = $row['codproveedor'];
        $mapping->idempresa = (int)$row['idempresa'];
        $mapping->emisor_rfc = $row['emisor_rfc'];
        $mapping->cfdi_referencia = $row['cfdi_referencia'];
        $mapping->referencia = $row['referencia'];
        $mapping->match_method = $row['match_method'];
        $mapping->confidence = (float)$row['confidence'];
        $mapping->created_at = $row['created_at'];
        $mapping->updated_at = $row['updated_at'];

        return $mapping;
    }
}

class ProductMapping
{
    public int $id = 0;
    public string $codproveedor = '';
    public int $idempresa = 0;
    public string $emisor_rfc = '';
    public string $cfdi_referencia = '';
    public string $referencia = '';
    public string $match_method = '';
    public float $confidence = 0.0;
    public string $created_at = '';
    public string $updated_at = '';

    public function getProduct(): ?Producto
    {
        $product = new Producto();

        if ($product->load($this->referencia)) {
            return $product;
        }

        return null;
    }

    public function getSupplier(): ?Proveedor
    {
        $supplier = new Proveedor();

        if ($supplier->load($this->codproveedor)) {
            return $supplier;
        }

        return null;
    }
}
