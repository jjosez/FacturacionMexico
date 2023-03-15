<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use Closure;

/**
 * @method addButton(string $string, string[] $array)
 */
class EditFacturaCliente
{
    /*  public function createViews(): Closure
      {
          return function() {
              $cfdiButton = [
                  'action' => 'cfdi-action',
                  'color' => 'info',
                  'icon' => 'fas fa-file-invoice',
                  'label' => 'CFDI',
                  'type' => 'action',
              ];
              $this->addButton($this->getMainViewName(), $cfdiButton);
          };
      } */

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


    public function execPreviousAction($action = ''): Closure
    {
        return function ($action = '') {
            switch ($action) {
                case 'cfdi-action':
                    $code = $this->request->query->get('code');
                    $this->redirect('EditCfdiCliente?invoice=' . $code);
                    break;
            }
        };
    }
}
