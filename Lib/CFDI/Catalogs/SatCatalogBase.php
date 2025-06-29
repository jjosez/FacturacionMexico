<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogs;

abstract class SatCatalogBase
{
    protected string $catalogName;
    protected ?array $data = null;

    public function __construct()
    {
        $this->catalogName = $this->fileName();
    }

    abstract protected function fileName(): string;

    /**
     * Carga todos los elementos del catálogo desde archivo JSON.
     */
    public function all(): array
    {
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
}
