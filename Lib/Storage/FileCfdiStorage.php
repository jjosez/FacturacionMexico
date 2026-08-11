<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Storage;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiStorageException;

final class FileCfdiStorage implements CfdiStorageInterface
{
    private const BASE_PATH = FS_FOLDER . '/MyFiles/FacturacionMexico/cfdi/';

    public function save(string $uuid, string $xml): string
    {
        $relativePath = date('Y') . '/' . date('m') . '/' . $uuid . '.xml';
        $fullPath = self::BASE_PATH . $relativePath;
        $directory = dirname($fullPath);

        if (!Tools::folderCheckOrCreate($directory)) {
            throw new CfdiStorageException('No se pudo crear el almacenamiento de CFDI.');
        }

        if (file_put_contents($fullPath, $xml) === false) {
            throw new CfdiStorageException('No se pudo guardar el XML del CFDI.');
        }

        return $relativePath;
    }

    public function get(string $uuid): ?string
    {
        $path = $this->findPath($uuid);
        if ($path === null) {
            return null;
        }

        $xml = file_get_contents($path);
        return $xml === false ? null : $xml;
    }

    public function exists(string $uuid): bool
    {
        return $this->findPath($uuid) !== null;
    }

    public function delete(string $uuid): bool
    {
        $path = $this->findPath($uuid);
        return $path === null || unlink($path);
    }

    private function findPath(string $uuid): ?string
    {
        $pattern = self::BASE_PATH . '*/*/' . $uuid . '.xml';
        $paths = glob($pattern);
        if (!empty($paths)) {
            return $paths[0];
        }

        // Existing installations store the relative filename in the metadata model.
        $cfdi = new CfdiCliente();
        if ($cfdi->loadFromUuid($uuid) && !empty($cfdi->filename)) {
            $legacyPath = FS_FOLDER . '/MyFiles/CFDI/customer/' . $cfdi->filename;
            if (is_file($legacyPath)) {
                return $legacyPath;
            }
        }

        return null;
    }
}
