<?php

namespace FacturaScripts\Plugins\FacturacionMexico;

define('CFDI_DIR', 'MyFiles' . DIRECTORY_SEPARATOR . 'CFDI');
define('CFDI_CATALOGS_DIR', FS_FOLDER . '/Plugins/FacturacionMexico/Lib/CFDI/Catalogos/Data');
define('CFDI_CERT_DIR', CFDI_DIR . DIRECTORY_SEPARATOR . 'certs');
define('CFDI_XSLT_DIR', CFDI_DIR . DIRECTORY_SEPARATOR . 'resources');
define('CFDI_XSLT_URL', 'http://www.sat.gob.mx/sitio_internet/cfd/3/cadenaoriginal_3_3/cadenaoriginal_3_3.xslt');

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;

class Init extends InitClass
{

    public function init()
    {
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
    }

    public function update()
    {
        $this->setMetodosDePago();
        $this->setEstadosDocumento();
    }

    protected function setMetodosDePago()
    {
        $formaPagoFiltro = ['01','02','03','04', '28', '99'];
        $formaPago = new FormaPago();

        foreach (CfdiCatalogo::formaPago()->all() as $satFormaPago) {
            if (false === in_array($satFormaPago->id, $formaPagoFiltro)) {
                continue;
            }

            if ($formaPago->loadFromCode($satFormaPago->id)) {
                continue;
            }

            $formaPago->codpago = $satFormaPago->id;
            $formaPago->descripcion = $satFormaPago->descripcion;
            $formaPago->pagado = true;
            $formaPago->save();
        }
    }

    protected function setEstadosDocumento()
    {
        $where = [new DataBaseWhere('nombre', 'Timbrada')];

        $estadoDocumento = new EstadoDocumento();

        if (! $estadoDocumento->loadFromCode('',$where)) {
            $estadoDocumento->actualizastock = -1;
            $estadoDocumento->bloquear = true;
            $estadoDocumento->editable = false;
            $estadoDocumento->nombre = 'Timbrada';
            $estadoDocumento->tipodoc = 'FacturaCliente';

            $estadoDocumento->save();
        }
    }
}
