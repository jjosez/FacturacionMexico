<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Plugins\FacturacionMexico\Contract\InvoiceValidator;

class GlobalValidator implements InvoiceValidator
{

    public static function validate(BusinessDocument $document): void
    {
        // TODO: Implement validate() method.
    }
}
