<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Catalogs\SatCatalogo;

class CfdiCatalogo
{
    public static function estadoCfdi(): array
    {
        return [
            ['code' => 'Timbrado', 'description' => 'Timbrado'],
            ['code' => 'Cancelado', 'description' => 'Cancelado']
        ];
    }

    public static function formaPago(): SatCatalogo
    {
        return new SatCatalogo('c_FormaPago.json');
    }

    public static function regimenFiscal(): SatCatalogo
    {
        return new SatCatalogo('c_RegimenFiscal.json');
    }

    public static function tipoCfdi(): array
    {
        return [
            ['code' => 'I', 'description' => 'Ingreso'],
            ['code' => 'E', 'description' => 'Egreso']
        ];
    }

    public function tipoRelacion(): SatCatalogo
    {
        return new SatCatalogo('c_TipoRelacion.json');
    }

    public static function usoCfdi(): SatCatalogo
    {
        return new SatCatalogo('c_UsoCFDI.json');
    }
}
