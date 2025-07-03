<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use Closure;
use FacturaScripts\Dinamic\Lib\CFDI\CfdiCatalogo;

class ListFormaPago
{
    use FormaPagoControllerTrait;

    public function createViews(): \Closure
    {
        return function () {
            return $this->loadClaveSatWidget('ListFormaPago');
        };
    }
}
