<?php

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
