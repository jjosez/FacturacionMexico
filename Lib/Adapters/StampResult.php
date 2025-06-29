<?php

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
