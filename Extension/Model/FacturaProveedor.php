<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor as FacturaProveedorModel;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Status\StatusService;

class FacturaProveedor
{
    public function save(): Closure
    {
        return function () {
            /** @var FacturaProveedorModel $this */
            if (empty($this->idfactura)) {
                return true;
            }

            $cfdi = new CfdiProveedor();
            if ($cfdi->loadWhereEq('idfactura', $this->idfactura)) {
                (new StatusService())->syncInvoiceStatus($cfdi, $this);
            }

            return true;
        };
    }
}
