<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\ValidationResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\InvoiceValidator;

class EgresoValidator implements InvoiceValidator
{
    public static function validate(BusinessDocument $document): ValidationResult
    {
        $result = new ValidationResult();

        if (false === $document instanceof FacturaCliente) {
            $result->addMessage('El documento no es una factura válida.');
            return $result;
        }

        if (empty($document->cifnif)) {
            $result->addMessage('El RFC del cliente no puede estar vacío.');
            return $result;
        }

        if (false === Validator::validateRFC($document->cifnif)) {
            $result->addMessage('El RFC del cliente no tiene un formato válido.');
            return $result;
        }

        if (false === Validator::validateFormaPago($document)) {
            $result->addMessage('El campo FormaPago no contiene un valor del catálogo c_FormaPago.');
            return $result;
        }

        if (false === Validator::validateCreationToStampDate($document)) {
            $result->addMessage('La factura excede el límite de 3 días desde su fecha de creación para ser timbrada.');
            return $result;
        }

        return $result;
    }
}
