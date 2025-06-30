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

class CfdiBuildResult
{
    private readonly string $xml;
    private readonly string $buildMessage;
    private readonly bool $buildError;

    public function __construct(
        string $xml,
        string $buildMessage,
        bool $buildError,
    ) {
        $this->xml = $xml;
        $this->buildError = $buildError;
        $this->buildMessage = $buildMessage;
    }

    public function getXml(): string
    {
        return $this->xml;
    }

    public function getBuildMessage(): string
    {
        return $this->buildMessage;
    }

    public function hasError(): bool
    {
        return $this->buildError;
    }
}
