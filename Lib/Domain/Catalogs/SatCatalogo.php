<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Catalogs;

class SatCatalogo
{
    protected $catalogName;

    public function __construct(string $catalogName)
    {
        $this->catalogName = $catalogName;
    }

    /**
     * Returns all the available options
     *
     * @return array
     */
    public function all()
    {
        if (empty($this->catalogName)) {
            throw new \InvalidArgumentException('Error en el nombre del catalogo');
        }

        $path = CFDI_CATALOGS_DIR . DIRECTORY_SEPARATOR . $this->catalogName;
        $jsonfile = file_get_contents($path);

        $result = json_decode($jsonfile, false);

        return $result;
    }

    /**
     * Returns the value of key if exists else return false
     *
     * @return mixed
     */
    public function get($key)
    {
        foreach($this->all() as $item) {
            if($item->id == $key) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Returns the full descripption of key if exists else return empty strign
     *
     * @return string
     */
    public function getDescripcion($key)
    {
        foreach($this->all() as $item) {
            if($item->id == $key) {
                return "{$item->id} - {$item->descripcion}";
            }
        }

        return '';
    }
}
