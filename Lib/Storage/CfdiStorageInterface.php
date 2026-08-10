<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Storage;

interface CfdiStorageInterface
{
    public function save(string $uuid, string $xml): string;

    public function get(string $uuid): ?string;

    public function exists(string $uuid): bool;

    public function delete(string $uuid): bool;
}
