<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Contract;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\ValidationResult;

interface InvoiceValidator
{
    static function validate(BusinessDocument $document): ValidationResult;
}
