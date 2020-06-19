<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos;

/**
 *
 * @author Juan Jose Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class TipoCfdi
{

    /**
     * Returns all the available options
     *
     * @return array
     */
    public static function all()
    {
        return [
            ['code' => 'I', 'description' => 'Ingreso'],
            ['code' => 'E', 'description' => 'Egreso']
        ];
    }

    /**
     * Returns the default value
     *
     * @return string
     */
    public static function defaultValue()
    {
        return 'I';
    }
}
