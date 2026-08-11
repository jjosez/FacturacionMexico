<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage;

use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiScope;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiStorageException;

final class FileCfdiStorage implements CfdiStorageInterface
{
    private const BASE_PATH = FS_FOLDER . '/MyFiles/FacturacionMexico/cfdi/';

    public function save(CfdiScope $scope, string $uuid, string $xml): string
    {
        $fullPath = $this->currentPath($scope, $uuid);
        $directory = dirname($fullPath);

        if (!Tools::folderCheckOrCreate($directory)) {
            throw new CfdiStorageException('No se pudo crear el almacenamiento de CFDI.');
        }

        if (file_put_contents($fullPath, $xml) === false) {
            throw new CfdiStorageException('No se pudo guardar el XML del CFDI.');
        }

        return $uuid;
    }

    public function get(CfdiScope $scope, string $uuid): ?string
    {
        $path = $this->findPath($scope, $uuid);
        if ($path === null) {
            return null;
        }

        $xml = file_get_contents($path);
        return $xml === false ? null : $xml;
    }

    public function exists(CfdiScope $scope, string $uuid): bool
    {
        return $this->findPath($scope, $uuid) !== null;
    }

    public function delete(CfdiScope $scope, string $uuid): bool
    {
        $path = $this->findPath($scope, $uuid);
        return $path === null || unlink($path);
    }

    private function currentPath(CfdiScope $scope, string $uuid): string
    {
        return self::BASE_PATH . $scope->value . '/' . date('Y') . '/' . date('m') . '/' . $uuid . '.xml';
    }

    private function findPath(CfdiScope $scope, string $uuid): ?string
    {
        $pattern = self::BASE_PATH . $scope->value . '/*/*/' . $uuid . '.xml';
        $paths = glob($pattern);
        if (!empty($paths)) {
            return $paths[0];
        }

        return null;
    }
}
