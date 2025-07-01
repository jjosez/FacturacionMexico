<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Plugins\FacturacionMexico\Contract\InvoiceValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\ValidationResult;

class EgresoValidator implements InvoiceValidator
{
    public static function validate(BusinessDocument $document): ValidationResult
    {
        $errors = [];

        // Validar receptor
        if (empty($document->cifnif)) {
            $errors[] = "El RFC del cliente no puede estar vacío.";
        }

        if (false === Validator::validateRFC($document->cifnif)) {
            $errors[] = "El RFC del cliente no tiene un formato válido.";
        }

        // Validar uso CFDI
        if (empty($documento->usocfdi)) {
            $errors[] = "Debes seleccionar un uso de CFDI.";
        }

        // Validar forma y método de pago
        if (empty($documento->formapago)) {
            $errors[] = "Debes indicar la forma de pago.";
        }

        if (empty($documento->metodopago)) {
            $errors[] = "Debes indicar el método de pago.";
        }

        // Validar moneda
        if (empty($documento->coddivisa)) {
            $errors[] = "La moneda no puede estar vacía.";
        }

        // Validar tipo cambio si la moneda no es MXN
        if ($documento->coddivisa !== 'MXN' && floatval($documento->tasaconv) <= 0) {
            $errors[] = "Debes capturar el tipo de cambio para moneda extranjera.";
        }

        // Si es egreso, validar relación CFDI
        if (property_exists($documento, 'tipocomprobante') && $documento->tipocomprobante === 'E') {
            if (empty($documento->uuid_relacionado)) {
                $errors[] = "La nota de crédito debe estar relacionada a un CFDI (UUID).";
            }

            if (empty($documento->tiporelacion)) {
                $errors[] = "Debes indicar el tipo de relación CFDI (como '01' para devolución).";
            }
        }

        if (!empty($errors)) {
            throw new Exception("Errores de validación CFDI:\n- " . implode("\n- ", $errors));
        }
    }
}
