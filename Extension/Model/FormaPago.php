<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;

/**
 * @property $clavesat
 */
class FormaPago
{
    public function clavesat(): Closure
    {
        return function () {
            return $this->clavesat;
        };
    }
}
