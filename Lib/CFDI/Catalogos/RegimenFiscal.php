<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos;

/**
 * This class centralizes all common method for VAT Regime.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class RegimenFiscal extends SatCatalogo
{
    public function __construct(string $catalogName = 'c_RegimenFiscal.json')
    {
        parent::__construct($catalogName);
    }
}
