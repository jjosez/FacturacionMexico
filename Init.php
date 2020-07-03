<?php

namespace FacturaScripts\Plugins\FacturacionMexico;

define('CFDI_DIR', 'MyFiles' . DIRECTORY_SEPARATOR . 'CFDI');
define('CFDI_CATALOGS_DIR', FS_FOLDER . '/Plugins/FacturacionMexico/Lib/CFDI/Catalogos/Data');
define('CFDI_CERT_DIR', CFDI_DIR . DIRECTORY_SEPARATOR . 'certs');
define('CFDI_XSLT_DIR', CFDI_DIR . DIRECTORY_SEPARATOR . 'resources');
define('CFDI_XSLT_URL', 'http://www.sat.gob.mx/sitio_internet/cfd/3/cadenaoriginal_3_3/cadenaoriginal_3_3.xslt');

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Base\InitClass;

class Init extends InitClass
{

    public function init()
    {
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
        $this->loadExtension(new Extension\Model\Producto());
    }

    public function update()
    {

    }
}