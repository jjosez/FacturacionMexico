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

use FacturaScripts\Core\Lib\ExtendedController;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;

/**
 * Controller to list the items in the TerminalPOS model
 *
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
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'CFDI Clientes';
        $pagedata['icon'] = 'fas fa-file-invoice';
        $pagedata['menu'] = 'CFDI';

        return $pagedata;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createMainView();
    }

    protected function createMainView($viewName = 'ListCfdiCliente'): void
    {
        $this->addView($viewName, 'CfdiCliente', 'CFDI Clientes', 'fas fa-file-invoice');
        $this->addSearchFields($viewName, ['razonreceptor','rfcreceptor', 'uuid']);
        $this->addOrderBy($viewName, ['fecha'], 'date', 2);

        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'razonsocial');
        $this->addFilterPeriod($viewName, 'date', 'period', 'fecha');
        $this->addFilterSelect($viewName, 'tipo', 'type', 'tipocfdi', CfdiCatalogo::tipoCfdi());
        $this->addFilterSelect($viewName, 'estado', 'state', 'estado', CfdiCatalogo::estadoCfdi());

        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }
}
