<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
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
namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters;

class CfdiSatStatus
{
    public string $cfdiStatus;
    public string $cancelableStatus;
    public string $cancellationStatus;
    public string $statusCode;
    public string $statusMessage;

    public function __construct(
        string $cfdiStatus,
        string $cancelableStatus,
        string $cancellationStatus,
        string $statusCode = '',
        string $statusMessage = ''
    ) {
        $this->cfdiStatus = $cfdiStatus;
        $this->cancelableStatus = $cancelableStatus;
        $this->cancellationStatus = $cancellationStatus;
        $this->statusCode = $statusCode;
        $this->statusMessage = $statusMessage;
    }
}
