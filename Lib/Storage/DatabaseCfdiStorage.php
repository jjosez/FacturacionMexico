<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Storage;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiStorageException;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiData;

final class DatabaseCfdiStorage implements CfdiStorageInterface
{
    public function save(string $uuid, string $xml): string
    {
        $cfdi = new CfdiCliente();
        if (!$cfdi->loadFromUuid($uuid)) {
            throw new CfdiStorageException('No se encontró el CFDI para guardar su XML.');
        }

        $data = new CfdiData();
        $data->loadWhereEq('uuid', $uuid);
        $data->cfdi_id = $cfdi->id;
        $data->uuid = $uuid;
        $data->xml = $xml;

        if (!$data->save()) {
            throw new CfdiStorageException('No se pudo guardar el XML del CFDI.');
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
