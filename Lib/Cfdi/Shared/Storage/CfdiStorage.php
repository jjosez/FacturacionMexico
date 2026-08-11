<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage;

use FacturaScripts\Plugins\FacturacionMexico\Lib\CfdiSettings;

final class CfdiStorage
{
    public static function get(): CfdiStorageInterface
    {
        return match (CfdiSettings::storageType()) {
            'database' => new DatabaseCfdiStorage(),
            default => new FileCfdiStorage(),
        };
    }
}
