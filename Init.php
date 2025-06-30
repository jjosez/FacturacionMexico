<?php

namespace FacturaScripts\Plugins\FacturacionMexico;

define('CFDI_DIR', 'MyFiles' . DIRECTORY_SEPARATOR . 'CFDI');
define('CFDI_CATALOGS_DIR', FS_FOLDER . '/Plugins/FacturacionMexico/Lib/CFDI/Catalogs/Data');
define('CFDI_CERT_DIR', CFDI_DIR . DIRECTORY_SEPARATOR . 'certs');
define('CFDI_XSLT_DIR', CFDI_DIR . DIRECTORY_SEPARATOR . 'resources');
define('CFDI_XSLT_URL', 'http://www.sat.gob.mx/sitio_internet/cfd/3/cadenaoriginal_3_3/cadenaoriginal_3_3.xslt');

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Plugins\FacturacionMexico\Model\Base\CfdiCatalogo;

class Init extends InitClass
{

    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
    }

    public function update(): void
    {
        $this->setMetodosDePago();
        $this->setEstadosDocumento();
    }

    protected function setMetodosDePago(): void
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

    protected function setEstadosDocumento(): void
    {
        $where = [
            new DataBaseWhere('nombre', 'Cancelada'),
            new DataBaseWhere('tipodoc', 'FacturaCliente')
        ];

        $canceledStatus = new EstadoDocumento();

        if (! $canceledStatus->loadFromCode('',$where)) {
            $canceledStatus->actualizastock = 1;
            $canceledStatus->bloquear = true;
            $canceledStatus->editable = false;
            $canceledStatus->nombre = 'Cancelada';
            $canceledStatus->tipodoc = 'FacturaCliente';

            $canceledStatus->save();
        }
    }

    public function uninstall(): void
    {
    }
}
