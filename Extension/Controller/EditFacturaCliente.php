<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use Closure;

/**
 * @method addButton(string $string, string[] $array)
 * @method getModel()
 * @method redirect(string $string)
 * @property $request
 */
class EditFacturaCliente
{
    public function createViews(): Closure
    {
        return function () {
            $this->addButton('main', [
                'action' => 'EditCfdiCliente?invoice=' . $this->getModel()->primaryColumnValue(),
                'color' => 'info',
                'icon' => 'fas fa-file-invoice',
                'label' => 'CFDI',
                'type' => 'link'
            ]);
            $this->addButton('main', [
                'action' => 'test-action',
                'icon' => 'fas fa-question',
                'label' => 'test'
            ]);
        };
    }


    public function execPreviousAction(): Closure
    {
        return function ($action = '') {
            if ('cfdi-action' === $action) {
                $code = $this->request->query->get('code');
                $this->redirect('EditCfdiCliente?invoice=' . $code);
            }
        };
    }
}
