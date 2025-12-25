<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\ValidationResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\InvoiceValidator;

class GlobalValidator implements InvoiceValidator
{

    public static function validate(BusinessDocument $document): ValidationResult
    {
        $result = new ValidationResult();

        if (false === $document instanceof FacturaCliente) {
            $result->addMessage('El documento no es una factura válida.');
            return $result;
        }

        if (false === Validator::validateGlobalInvoiceCustomer($document)) {
            $result->addMessage("El RFC XAXX010101000 o la Razón social PUBLICO EN GENERAL no coinciden para la factura global.");
        }

        if (false === Validator::validateFormaPago($document)) {
            $result->addMessage('El campo FormaPago no contiene un valor del catálogo c_FormaPago.');
            return $result;
        }

        if (false === Validator::validateCreationToStampDate($document)) {
            $result->addMessage('La factura excede el límite de 3 días desde su fecha de creación para ser timbrada.');
            return $result;
        }

        if (1 >= count($document->parentDocuments())) {
            $result->addMessage('El documento no es una factura global.');
            return $result;
        }

        return $result;
    }
}
