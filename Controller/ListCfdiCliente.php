<?php
/**
 * This file is part of POS plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController;
use FacturaScripts\Core\Model\EstadoDocumento;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\DeliveryNoteInvoiceGenerator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;

/**
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class ListCfdiCliente extends ExtendedController\ListController
{

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'CFDI';
        $data['title'] = 'CFDI Clientes';
        $data['icon'] = 'fas fa-file-invoice';

        return $data;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createMainView();
        $this->createPendingInvoicesView();
        $this->createPendingNotesView();
    }

    protected function createMainView($viewName = 'ListCfdiCliente'): void
    {
        $this->addView($viewName, 'CfdiCliente', 'CFDIs', 'fas fa-file-invoice');
        $this->addSearchFields($viewName, ['receptor_nombre', 'receptor_rfc', 'uuid']);
        $this->addOrderBy($viewName, ['fecha_timbrado'], 'date', 2);
        $this->addOrderBy($viewName, ['fecha_emision'], 'date', 2);

        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'razonsocial');
        $this->addFilterPeriod($viewName, 'date', 'period', 'fecha_timbrado');
        $this->addFilterSelect($viewName, 'tipo', 'type', 'tipo', CfdiCatalogo::tipoCfdi());
        $this->addFilterSelect($viewName, 'estado', 'state', 'estado', CfdiCatalogo::estadoCfdi());

        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    protected function createPendingInvoicesView($viewName = 'ListFacturaCliente'): void
    {
        $warehouses = $this->codeModel->all('almacenes', 'codalmacen', 'nombre');
        $series = $this->codeModel->all('series', 'codserie', 'descripcion');

        $this->addView($viewName, 'FacturaCliente', 'Pendientes', 'fas fa-file-invoice')
            ->addSearchFields(['codigo', 'nombrecliente', 'numero2', 'observaciones'])
            ->addOrderBy(['fecha', 'codigo', 'codserie'], 'date', 2)
            ->addFilterAutocomplete('codcliente', 'customer', 'codcliente', 'clientes', 'codcliente')
            ->addFilterSelect('warehouse', 'warehouse', 'codalmacen', $warehouses)
            ->addFilterSelect('serie', 'serie', 'codserie', $series)
            ->addFilterPeriod('date', 'period', 'fecha')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false);
    }

    protected function createPendingNotesView($viewName = 'ListAlbaranCliente'): void
    {
        $warehouses = $this->codeModel->all('almacenes', 'codalmacen', 'nombre');
        $series = $this->codeModel->all('series', 'codserie', 'descripcion');

        $this->addView($viewName, 'AlbaranCliente', 'delivery-note', 'fa-solid fa-receipt')
            ->addSearchFields(['codigo', 'nombrecliente', 'numero2', 'observaciones'])
            ->addOrderBy(['fecha'], 'date', 2)
            ->addOrderBy(['codigo'], 'code')
            ->addOrderBy(['total'], 'amount')
            ->addFilterAutocomplete('codcliente', 'customer', 'codcliente', 'clientes', 'codcliente')
            ->addFilterSelect('warehouse', 'warehouse', 'codalmacen', $warehouses)
            ->addFilterSelect('serie', 'serie', 'codserie', $series)
            ->addFilterPeriod('date', 'period', 'fecha')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false);

        $this->setValuesForInvoiceSerie($viewName, $series);

        // Botones de acciones
        $this->addButton($viewName, [
            'action' => 'group-delivery-notes',
            'color' => 'primary',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'group-and-invoice',
            'type' => 'modal'
        ]);

        $this->addButton($viewName, [
            'action' => 'approve-delivery-note',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-check',
            'label' => 'approve-document'
        ]);

        $this->addButton($viewName, [
            'action' => 'approve-delivery-note-same-date',
            'confirm' => 'true',
            'icon' => 'fa-solid fa-calendar-check',
            'label' => 'approve-document-same-date'
        ]);
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListFacturaCliente':
                $stampedState = CfdiSettings::stampedInvoiceStatus($this->empresa);
                $canceledState = CfdiSettings::canceledInvoiceStatus($this->empresa);

                $where = [
                    new DataBaseWhere('idestado', $stampedState, '!='),
                    new DataBaseWhere('idestado', $canceledState, '!='),
                ];

                $view->loadData('', $where);
                break;

            case 'ListAlbaranCliente':
                $invoicedStates = $this->invoicedOrCanceledStatus();
                $states = array_unique(array_filter(array_map(fn($p) => $p->idestado, $invoicedStates)));

                $where = [];
                if (!empty($states)) {
                    $where[] = Where::notIn('idestado', implode(',', $states));
                }

                $view->loadData('', $where);
                $view->model->invoice_date = date('Y-m-d');
                break;
            default:
                parent::loadData($viewName, $view);
        }
    }

    /**
     * Obtiene los ID de los estados de AlbaranCliente que generan FacturaCliente
     *
     * @return EstadoDocumento[]
     */
    protected function invoicedOrCanceledStatus(): array
    {
        $where = [
            Where::eq('tipodoc', 'AlbaranCliente'),
            Where::eq('editable', false)
        ];

        return EstadoDocumento::all($where);
    }

    /**
     * Ejecuta las acciones de los botones
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'group-delivery-notes':
                return $this->groupDeliveryNotesAction();

            case 'approve-delivery-note':
                return $this->approveDeliveryNoteAction(false);

            case 'approve-delivery-note-same-date':
                return $this->approveDeliveryNoteAction(true);

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Agrupa las remisiones seleccionadas para crear una factura
     */
    protected function groupDeliveryNotesAction(): bool
    {
        $codes = $this->request()->request->getArray('codes', []);

        if (!$this->validateFormToken()) {
            return true;
        }

        try {
            // Capturar parámetros del modal
            $properties = [];

            if ($fecha = $this->request->input('fecha')) {
                $properties['fecha'] = $fecha;
            }

            if ($codserie = $this->request->input('codserie')) {
                $properties['codserie'] = $codserie;
            }

            if ($this->request->input('global-invoice')) {
                $properties['cfdiglobal'] = true;
            }

            // Generar factura agrupada
            $generator = new DeliveryNoteInvoiceGenerator();
            $invoice = $generator->groupAndInvoice($codes, $properties);

            if ($invoice) {
                $this->redirect($invoice->url());
                return false;
            }

            return true;
        } catch (Exception $e) {
            // El servicio ya registra el error
            return true;
        }
    }

    /**
     * Aprueba las remisiones seleccionadas cambiando su estado
     */
    protected function approveDeliveryNoteAction(bool $sameDate): bool
    {
        $codes = $this->request()->request->getArray('codes');

        if (!$this->validateFormToken()) {
            return true;
        }

        try {
            $generator = new DeliveryNoteInvoiceGenerator();
            $generator->generateInvoices($codes, $sameDate);
            return true;
        } catch (Exception $e) {
            // El servicio ya registra el error
            return true;
        }
    }

    protected function setValuesForInvoiceSerie($viewName, $series)
    {
        $column = $this->tab($viewName)->columnModalForName('invoice-serie');
        if($column && $column->widget->getType() === 'select') {
            $column->widget->setValuesFromCodeModel($series);
        }
    }
}
