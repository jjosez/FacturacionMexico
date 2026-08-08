<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching;

use FacturaScripts\Dinamic\Model\Proveedor;

interface MatcherInterface
{
    public function match(array $concepto, Proveedor $supplier): ?MatchResult;

    public function getName(): string;

    public function getPriority(): int;
}
