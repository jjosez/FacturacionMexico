<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\ValidationResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\InvoiceValidator;

class IngresoValidator implements InvoiceValidator
{
    public static function validate(BusinessDocument $document): ValidationResult
    {
        $result = new ValidationResult();

        if (false === $document instanceof FacturaCliente) {
            $result->addMessage('El documento no es una factura válida.');
        }

        if (false === Validator::validateFormaPago($document)) {
            $result->addMessage('El campo FormaPago no contiene un valor del catálogo c_FormaPago.');
        }

        if (false === Validator::validateCreationToStampDate($document)) {
            $result->addMessage('La factura excede el límite de 3 días desde su fecha de creación para ser timbrada.');
        }

        if (Validator::validateGlobalInvoiceCustomer($document)) {
            $result->addMessage("El RFC XAXX010101000 y la Razon social PUBLICO EN GENERAL solo se puede usar en una factura global.");
        }

        return $result;
    }
}
