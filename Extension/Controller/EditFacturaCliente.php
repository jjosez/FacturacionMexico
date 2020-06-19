<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

class EditFacturaCliente
{
    public function createViews()
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
    }

    public function execPreviousAction($action = '')
    {
        return function($action = '') {
            switch ($action) {
                case 'cfdi-action':
                    $code = $this->request->query->get('code');
                    $this->redirect('EditCfdiCliente?invoice=' . $code);
                    break;
            }

            return parent::execPreviousAction($action);
        };
    }
}