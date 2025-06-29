<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Contract;
use FacturaScripts\Core\Model\Base\BusinessDocument;

interface InvoiceValidator
{
    static function validate(BusinessDocument $document): void;
}
