<?php 
namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos;

/**
 * This class centralizes all common method for VAT Regime.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class EstadoCfdi
{

    /**
     * Returns all the available options
     *
     * @return array
     */
    public static function all()
    {
        return [
            ['code' => 'T', 'description' => 'Timbrado'],
            ['code' => 'C', 'description' => 'Cancelado']
        ];
    }

    /**
     * Returns the default value
     *
     * @return string
     */
    public static function defaultValue()
    {
        return 'T';
    }
}
