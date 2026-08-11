<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiScope;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiStorageException;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiProveedorData;

final class DatabaseCfdiStorage implements CfdiStorageInterface
{
    public function save(CfdiScope $scope, string $uuid, string $xml): string
    {
        $data = $this->data($scope);
        $data->loadWhereEq('uuid', $uuid);
        if (!$data->exists()) {
            $data->cfdi_id = $this->cfdiId($scope, $uuid);
        }
        $data->uuid = $uuid;
        $data->xml = $xml;

        if (!$data->save()) {
            throw new CfdiStorageException('No se pudo guardar el XML del CFDI.');
        }

        return $uuid;
    }

    public function get(CfdiScope $scope, string $uuid): ?string
    {
        $data = $this->data($scope);
        if (!$data->loadWhereEq('uuid', $uuid)) {
            return null;
        }

        return $data->xml;
    }

    public function exists(CfdiScope $scope, string $uuid): bool
    {
        $data = $this->data($scope);
        return $data->loadWhereEq('uuid', $uuid);
    }

    public function delete(CfdiScope $scope, string $uuid): bool
    {
        $data = $this->data($scope);
        return !$data->loadWhereEq('uuid', $uuid) || $data->delete();
    }

    private function cfdiId(CfdiScope $scope, string $uuid): int
    {
        $cfdi = match ($scope) {
            CfdiScope::CUSTOMER => new CfdiCliente(),
            CfdiScope::SUPPLIER => new CfdiProveedor(),
        };

        if (!$cfdi->loadFromUuid($uuid)) {
            throw new CfdiStorageException('No se encontró el CFDI para guardar su XML.');
        }

        return (int) $cfdi->id;
    }

    private function data(CfdiScope $scope): ModelClass
    {
        return match ($scope) {
            CfdiScope::CUSTOMER => new CfdiData(),
            CfdiScope::SUPPLIER => new CfdiProveedorData(),
        };
    }
}
