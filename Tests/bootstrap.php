<?php

$root = dirname(__DIR__, 3);

if (!defined('FS_FOLDER')) {
    define('FS_FOLDER', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'facturacion-mexico-' . bin2hex(random_bytes(8)));
}

require_once $root . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

register_shutdown_function(static function (): void {
    if (is_dir(FS_FOLDER)) {
        \FacturaScripts\Core\Tools::folderDelete(FS_FOLDER);
    }
});
