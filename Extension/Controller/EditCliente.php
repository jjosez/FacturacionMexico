<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use Closure;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;

/**
 * @method addButton(string $string, string[] $array)
 */
class EditCliente
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = $this->getMainViewName();
            $column = $this->views[$viewName]->columnForName('regimenfiscal');
            if ($column && $column->widget->getType() === 'select') {

                foreach (CfdiCatalogo::regimenFiscal()->all() as $item) {
                    $regimenValues[] = ['value' => $item->id, 'title' => $item->id . ' - ' . $item->descripcion];
                }
                $column->widget->setValuesFromArray($regimenValues, false, true);
            }

            $column = $this->views[$viewName]->columnForName('usocfdi');
            if ($column && $column->widget->getType() === 'select') {

                foreach (CfdiCatalogo::usoCfdi()->all() as $item) {
                    $usoValues[] = ['value' => $item->id, 'title' => $item->id . ' - ' . $item->descripcion];
                }
                $column->widget->setValuesFromArray($usoValues, false, true);
            }
        };
    }
}
