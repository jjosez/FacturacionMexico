<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Contract\InvoiceValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\ValidationResult;

class IngresoValidator implements InvoiceValidator
{
    public static function validate(BusinessDocument $document): ValidationResult
    {
        $result = new ValidationResult();

        if (false === $document instanceof FacturaCliente) {
            $result->addMessage('El documento no es una factura válida.');
            return $result;
        }

        if (Validator::validateGlobalInvoiceCustomer($document)) {
            $result->addMessage("El RFC XAXX010101000 y la Razon social PUBLICO EN GENERAL solo se puede usar en una factura global.");
            return $result;
        }

        return $result;
    }
}
