<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Validation;

use FacturaScripts\Dinamic\Model\Cliente;

class CustomerValidator
{
    private static function cleanRfc(string $rfc): string
    {
        return strtoupper(trim($rfc, " \t\n\r\0\x0B.,"));
    }

    public static function detectTipoPersona(string $rfc): string
    {
        $rfc = self::cleanRfc($rfc);

        if ($rfc === 'XAXX010101000') {
            return 'generico';
        }

        if ($rfc === 'XEXX010101000') {
            return 'extranjero';
        }

        if (!self::validateRfcFormat($rfc)) {
            return 'invalido';
        }

        $length = strlen($rfc);

        if ($length === 13) {
            return 'fisica';
        }

        if ($length === 12) {
            return 'moral';
        }

        return 'invalido';
    }

    public static function isPersonaFisica(string $rfc): bool
    {
        return self::detectTipoPersona($rfc) === 'fisica';
    }

    public static function isPersonaMoral(string $rfc): bool
    {
        return self::detectTipoPersona($rfc) === 'moral';
    }

    public static function isRfcGenerico(string $rfc): bool
    {
        $tipo = self::detectTipoPersona($rfc);
        return $tipo === 'generico' || $tipo === 'extranjero';
    }

    public static function validateRfcFormat(string $rfc): bool
    {
        $rfc = self::cleanRfc($rfc);

        $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/';

        return preg_match($pattern, $rfc) === 1;
    }

    public static function validateForCfdi(Cliente $cliente): array
    {
        $errors = [];

        if (empty($cliente->cifnif)) {
            $errors[] = 'El cliente no tiene RFC registrado';
        } elseif (!self::validateRfcFormat($cliente->cifnif)) {
            $errors[] = 'El RFC del cliente tiene un formato inválido';
        }

        if (empty($cliente->domicilioFiscal())) {
            $errors[] = 'El cliente no tiene código postal del domicilio fiscal';
        }

        if (empty($cliente->regimenFiscal())) {
            $errors[] = 'El cliente no tiene régimen fiscal asignado';
        }

        if (empty($cliente->usoCfdi())) {
            $errors[] = 'El cliente no tiene uso de CFDI asignado';
        }

        return $errors;
    }

    public static function isValidForCfdi(Cliente $cliente): bool
    {
        return empty(self::validateForCfdi($cliente));
    }

    public static function getPersonaInfo(Cliente $cliente): array
    {
        $tipo = self::detectTipoPersona($cliente->cifnif);

        $descripciones = [
            'fisica' => 'Persona Física',
            'moral' => 'Persona Moral',
            'generico' => 'RFC Genérico Nacional',
            'extranjero' => 'RFC Genérico Extranjero',
            'invalido' => 'RFC Inválido'
        ];

        return [
            'tipo' => $tipo,
            'descripcion' => $descripciones[$tipo] ?? 'Desconocido',
            'valido' => in_array($tipo, ['fisica', 'moral', 'generico', 'extranjero'])
        ];
    }

    public static function validateRfcNameCoherence(Cliente $cliente): bool
    {
        if (self::isRfcGenerico($cliente->cifnif)) {
            return true;
        }

        $rfc = self::cleanRfc($cliente->cifnif);
        $nombre = strtoupper(trim($cliente->nombre, " \t\n\r\0\x0B.,"));

        if (empty($rfc) || empty($nombre)) {
            return false;
        }

        $tipo = self::detectTipoPersona($rfc);
        $letrasRfc = substr($rfc, 0, $tipo === 'moral' ? 3 : 4);

        return $letrasRfc[0] === $nombre[0];
    }

    public static function cleanFiscalString(string $string): string
    {
        return strtoupper(trim($string, " \t\n\r\0\x0B.,"));
    }
}
