<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019-2025 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Exception;

use Exception;

/**
 * Excepción para errores de configuración de CFDI
 */
class CfdiConfigurationException extends Exception
{
    private array $missingSettings = [];

    public function __construct(string $message, array $missingSettings = [])
    {
        parent::__construct($message);
        $this->missingSettings = $missingSettings;
    }

    public function getMissingSettings(): array
    {
        return $this->missingSettings;
    }

    public function getUserFriendlyMessage(): string
    {
        if (empty($this->missingSettings)) {
            return $this->getMessage();
        }

        $message = "Configuración incompleta. Faltan los siguientes datos:\n\n";

        foreach ($this->missingSettings as $setting => $description) {
            $message .= "• {$description}\n";
        }

        $message .= "\nPor favor, configure estos valores en: Menú > CFDI > Configuración";

        return $message;
    }
}
