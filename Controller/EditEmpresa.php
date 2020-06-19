<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Controller\EditEmpresa as ParentController;
use FacturaScripts\Dinamic\Lib\CFDI\RegimenFiscal;

class EditEmpresa extends ParentController
{
    protected function setCustomWidgetValues()
    {         
        parent::setCustomWidgetValues();

        /// Load values option to Regimen Fiscal select input       
        $columnRegimenFiscal = $this->views['EditEmpresa']->columnForName('regimenfiscal');
        $columnRegimenFiscal->widget->setValuesFromArrayKeys(RegimenFiscal::all());
    }
}