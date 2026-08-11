<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class CfdiProveedorData extends ModelClass
{
    use ModelTrait;

    public $cfdi_id;
    public $uuid;
    public $xml;

    public static function primaryColumn(): string
    {
        return 'uuid';
    }

    public static function tableName(): string
    {
        return 'cfdis_proveedores_data';
    }
}
