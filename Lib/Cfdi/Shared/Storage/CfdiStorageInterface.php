<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiScope;

interface CfdiStorageInterface
{
    public function save(CfdiScope $scope, string $uuid, string $xml): string;

    public function get(CfdiScope $scope, string $uuid): ?string;

    public function exists(CfdiScope $scope, string $uuid): bool;

    public function delete(CfdiScope $scope, string $uuid): bool;
}
