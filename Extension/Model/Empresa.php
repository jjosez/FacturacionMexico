<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;

/**
 * @property bool $cfdi_tax_regime
 */
class Empresa
{
    public function regimenFiscal(): Closure
    {
        return function () {
            return $this->cfdi_tax_regime;
        };
    }
}
