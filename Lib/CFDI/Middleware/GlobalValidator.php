<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Contract\InvoiceValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\ValidationResult;

class GlobalValidator implements InvoiceValidator
{

    public static function validate(BusinessDocument $document): ValidationResult
    {
        $result = new ValidationResult();

        if (false === $document instanceof FacturaCliente) {
            $result->addMessage('El documento no es una factura válida.');
            return $result;
        }

        if (1 >= count($document->parentDocuments())) {
            $result->addMessage('El documento no es una factura global.');
            return $result;
        }

        return $result;
    }
}
