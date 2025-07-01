<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware;

use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiSettings;

class Validator
{
    public static function validateRFC(string $rfc): bool
    {
        $rfc = strtoupper(trim($rfc));

        // Patrón oficial SAT: 3-4 letras, 6 dígitos de fecha, 3 caracteres homoclave
        $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/';

        return preg_match($pattern, $rfc) === 1;
    }

    public static function validateGlobalInvoiceCustomer(FacturaCliente $invoice)
    {
        return CfdiSettings::rfcGenerico() === $invoice->cifnif && CfdiSettings::razonSocialGenerico() === $invoice->nombrecliente;
    }
}
