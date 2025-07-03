<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;

/**
 * @method tab($VIEW_NAME)
 */
class EditFormaPago
{
    public function createViews(): \Closure
    {
        return function () {
            $column = $this->tab('EditFormaPago')->columnForName('clavesat');

            if ($column && $column->widget->getType() === 'select') {
                $catalog = CfdiCatalogo::formaPago();
                $customValues = [];

                foreach ($catalog->all() as $item) {
                    $customValues[] = [
                        'value' => $item->id,
                        'title' => "{$item->id} - {$item->descripcion}",
                    ];

                }

                $column->widget->setValuesFromArray($customValues);
                //$column->widget->setValuesFromArray($customValues, false, true);
            }
        };
    }
}
