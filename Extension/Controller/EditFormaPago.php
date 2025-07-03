<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

/**
 * @method tab($VIEW_NAME)
 */
class EditFormaPago
{
    use FormaPagoControllerTrait;
    public function createViews(): \Closure
    {
        return function () {
            return $this->loadClaveSatWidget('EditFormaPago');
        };
    }
}
