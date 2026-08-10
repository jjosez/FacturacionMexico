<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Storage;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiData;

final class DatabaseCfdiStorage implements CfdiStorageInterface
{
    public function save(string $uuid, string $xml): string
    {
        $cfdi = new CfdiCliente();
        if (!$cfdi->loadFromUuid($uuid)) {
            throw new \RuntimeException('No se encontró el CFDI para guardar su XML.');
        }

        $data = new CfdiData();
        $data->cfdi_id = $cfdi->id;
        $data->uuid = $uuid;
        $data->xml = $xml;

        if (!$data->save()) {
            throw new \RuntimeException('No se pudo guardar el XML del CFDI.');
        }

        return $uuid;
    }

    public function get(string $uuid): ?string
    {
        $data = new CfdiData();
        if (!$data->loadWhereEq('uuid', $uuid)) {
            return null;
        }

        return $data->xml;
    }

    public function exists(string $uuid): bool
    {
        $data = new CfdiData();
        return $data->loadWhereEq('uuid', $uuid);
    }

    public function delete(string $uuid): bool
    {
        $data = new CfdiData();
        return !$data->loadWhereEq('uuid', $uuid) || $data->delete();
    }
}
