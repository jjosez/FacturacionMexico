<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\SAT\Catalog;

abstract class SatCatalogBase
{
    private static array $staticCache = [];
    private static ?int $cacheTime = null;

    private const CACHE_TTL_SECONDS = 3600;

    protected string $catalogName;
    protected ?array $data = null;

    public function __construct()
    {
        $this->catalogName = $this->fileName();
    }

    abstract protected function fileName(): string;

    public static function clearCache(): void
    {
        self::$staticCache = [];
        self::$cacheTime = null;
    }

    public static function getCacheStats(): array
    {
        return [
            'cached_catalogs' => array_keys(self::$staticCache),
            'cache_age_seconds' => self::$cacheTime !== null ? (time() - self::$cacheTime) : null,
            'ttl_seconds' => self::CACHE_TTL_SECONDS,
        ];
    }

    public function all(): array
    {
        if ($this->isCacheValid()) {
            $this->data = self::$staticCache[$this->catalogName] ?? null;
        }

        if ($this->data !== null) {
            return $this->data;
        }

        $path = CFDI_CATALOGS_DIR . DIRECTORY_SEPARATOR . $this->catalogName;
        if (!file_exists($path)) {
            throw new \RuntimeException("No se encontró el catálogo: $path");
        }

        $jsonfile = file_get_contents($path);
        $this->data = json_decode($jsonfile, false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Error al decodificar JSON: ' . json_last_error_msg());
        }

        self::$staticCache[$this->catalogName] = $this->data;

        if (self::$cacheTime === null) {
            self::$cacheTime = time();
        }

        return $this->data;
    }

    public function get(string $key): object|null
    {
        foreach ($this->all() as $item) {
            if ($item->id === $key) {
                return $item;
            }
        }
        return null;
    }

    public function getDescription(string $key): string
    {
        $item = $this->get($key);
        return $item ? "{$item->id} - {$item->descripcion}" : '';
    }

    public function findBy(string $field, string $value): array
    {
        $results = [];

        foreach ($this->all() as $item) {
            if (isset($item->$field) && $item->$field === $value) {
                $results[] = $item;
            }
        }

        return $results;
    }

    public function search(string $query, string $field = 'descripcion'): array
    {
        $query = mb_strtolower($query, 'UTF-8');
        $results = [];

        foreach ($this->all() as $item) {
            if (isset($item->$field)) {
                $fieldValue = mb_strtolower($item->$field, 'UTF-8');

                if (mb_strpos($fieldValue, $query, 0, 'UTF-8') !== false) {
                    $results[] = $item;
                }
            }
        }

        return $results;
    }

    private function isCacheValid(): bool
    {
        if (!isset(self::$staticCache[$this->catalogName])) {
            return false;
        }

        if (self::$cacheTime === null) {
            return false;
        }

        return (time() - self::$cacheTime) < self::CACHE_TTL_SECONDS;
    }
}
