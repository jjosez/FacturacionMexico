<?php

use FacturaScripts\Core\Plugins;

$root = dirname(__DIR__, 3);
chdir($root);

require_once $root . '/Test/bootstrap.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
Plugins::init();
chdir(dirname(__DIR__));

if (!Plugins::isEnabled('FacturacionMexico')) {
    throw new RuntimeException('FacturacionMexico debe estar habilitado en la base de testing.');
}
