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

class StampResult
{
    private bool $error;
    private string $uuid;
    private string $xml;
    private string $message;
    private string $code;
    private string $detail;
    private bool $previous;

    public function __construct(
        bool $error,
        string $uuid = '',
        string $xml = '',
        string $message = '',
        string $code = '',
        string $detail = '',
        bool $previous = false
    )
    {
        $this->error = $error;
        $this->uuid = $uuid;
        $this->xml = $xml;
        $this->message = $message;
        $this->code = $code;
        $this->detail = $detail;
        $this->previous = $previous;
    }

    public function hasError(): bool
    {
        return $this->error;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getXml(): string
    {
        return $this->xml;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function hasPreviousStamp(): bool
    {
        return $this->previous;
    }
}
