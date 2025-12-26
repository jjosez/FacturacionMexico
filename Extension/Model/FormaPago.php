<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;

/**
 * Para modificar el comportamiento de modelos de otro plugins (o del core)
 * podemos crear una extensión de ese modelo.
 *
 * https://facturascripts.com/publicaciones/extensiones-de-modelos
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
