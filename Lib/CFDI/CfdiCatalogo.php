<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos\SatCatalogo;

class CfdiCatalogo
{
    public static function estadoCfdi()
    {
        return [
            ['code' => 'T', 'description' => 'Timbrado'],
            ['code' => 'C', 'description' => 'Cancelado']
        ];
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

    public function usoCfdi()
    {
        return new SatCatalogo('c_UsoCFDI.json');
    }
}