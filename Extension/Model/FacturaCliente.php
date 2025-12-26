<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;
use FacturaScripts\Dinamic\Model\FacturaCliente as FacturaClienteModel;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware\Validator;

/**
 * @property bool $factura_global
 * @property $cfdi_tax_regime
 */
class FacturaCliente
{
    public function isGlobalInvoice(): Closure
    {
        return function () {
            return $this->factura_global;
        };
    }

    public function test(): Closure
    {
        return function () {
            /** @var FacturaClienteModel $this */
            if ($this->isGlobalInvoice()) {
                return Validator::validateGlobalInvoice($this);
            }

            return true;
        };
    }
}
