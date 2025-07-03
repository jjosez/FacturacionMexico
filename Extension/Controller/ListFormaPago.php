<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use Closure;
use FacturaScripts\Dinamic\Lib\CFDI\CfdiCatalogo;

class ListFormaPago
{
    use FormaPagoControllerTrait;

    public function createViews(): Closure
    {
        return function () {
            $column = $this->tab('ListFormaPago')->columnForName('clavesat');

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
