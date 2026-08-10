<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor as FacturaProveedorModel;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\SupplierCfdiStatusService;

class FacturaProveedor
{
    public function save(): Closure
    {
        return function () {
            /** @var FacturaProveedorModel $this */
            if (empty($this->idfactura) || $this->getStatus()->nombre !== 'Recibida') {
                return true;
            }

            $cfdi = new CfdiProveedor();
            if ($cfdi->loadWhereEq('idfactura', $this->idfactura)) {
                (new SupplierCfdiStatusService())->markReceived($cfdi, $this);
            }

            return true;
        };
    }
}
