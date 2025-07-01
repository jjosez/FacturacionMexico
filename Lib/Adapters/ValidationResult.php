<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters;

class ValidationResult
{
    private bool $valid;
    private array $messages;

    public function __construct(bool $valid = true, array $messages = [])
    {
        $this->valid = $valid;
        $this->messages = $messages;
    }

    public function addMessage(string $message): void
    {
        $this->valid = false;
        $this->messages[] = $message;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getMessagesAsString(string $separator = PHP_EOL): string
    {
        return implode($separator, $this->messages);
    }
}
