<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogs\SatCatalogo;

class CfdiCatalogo
{
    public static function estadoCfdi()
    {
        return [
            ['code' => 'Timbrado', 'description' => 'Timbrado'],
            ['code' => 'Cancelado', 'description' => 'Cancelado']
        ];
    }

    public static function formaPago()
    {
        return new SatCatalogo('c_FormaPago.json');
    }

    public static function regimenFiscal()
    {
        return new SatCatalogo('c_RegimenFiscal.json');
    }

    public static function tipoCfdi()
    {
        return [
            ['code' => 'I', 'description' => 'Ingreso'],
            ['code' => 'E', 'description' => 'Egreso']
        ];
    }

    public function tipoRelacion()
    {
        return new SatCatalogo('c_TipoRelacion.json');
    }

    public static function usoCfdi()
    {
        return new SatCatalogo('c_UsoCFDI.json');
    }
}
