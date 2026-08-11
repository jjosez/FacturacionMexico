<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Validation;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\ValidationResult;

interface InvoiceValidator
{
    public static function validate(BusinessDocument $document): ValidationResult;
}
