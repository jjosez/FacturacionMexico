<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Filesystem;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\UploadedFile;

final class SupplierCfdiFileStorage
{
    public const DESTINATION_FOLDER = FS_FOLDER . '/MyFiles/CFDI/supplier/';

    public function store(UploadedFile $uploadFile): ?string
    {
        Tools::folderCheckOrCreate(self::DESTINATION_FOLDER);

        if (!$uploadFile->isValid()) {
            return null;
        }

        $destinationName = $uploadFile->getClientOriginalName();
        if (file_exists(self::DESTINATION_FOLDER . $destinationName)) {
            $destinationName = date('Ymd_His') . '_' . $destinationName;
        }

        return $uploadFile->move(self::DESTINATION_FOLDER, $destinationName)
            ? $destinationName
            : null;
    }

    public function read(string $fileName): string
    {
        $content = file_get_contents(self::DESTINATION_FOLDER . $fileName);
        if ($content === false) {
            throw new \Exception('No se pudo leer el archivo CFDI.');
        }

        return $content;
    }
}
