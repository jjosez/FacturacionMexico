<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware;

use FacturaScripts\Dinamic\Model\Cliente;

/**
 * Validador especializado para clientes en contexto de facturación CFDI
 * Incluye detección de tipo de persona (Física/Moral) basada en RFC
 */
class CustomerValidator
{
    /**
     * Limpia y normaliza un RFC eliminando espacios, comas, puntos y otros caracteres no deseados
     *
     * @param string $rfc
     * @return string RFC en mayúsculas y limpio
     */
    private static function cleanRfc(string $rfc): string
    {
        return strtoupper(trim($rfc, " \t\n\r\0\x0B.,"));
    }

    /**
     * Detecta si un RFC corresponde a una Persona Física o Persona Moral
     *
     * Regla SAT:
     * - Persona Física: RFC de 13 caracteres (4 letras + 6 dígitos + 3 homoclave)
     * - Persona Moral: RFC de 12 caracteres (3 letras + 6 dígitos + 3 homoclave)
     *
     * @param string $rfc
     * @return string 'fisica'|'moral'|'generico'|'extranjero'|'invalido'
     */
    public static function detectTipoPersona(string $rfc): string
    {
        $rfc = self::cleanRfc($rfc);

        // RFC genérico nacional
        if ($rfc === 'XAXX010101000') {
            return 'generico';
        }

        // RFC genérico extranjero
        if ($rfc === 'XEXX010101000') {
            return 'extranjero';
        }

        // Validar formato general
        if (!self::validateRfcFormat($rfc)) {
            return 'invalido';
        }

        // Detectar por longitud
        $length = strlen($rfc);

        if ($length === 13) {
            return 'fisica';
        }

        if ($length === 12) {
            return 'moral';
        }

        return 'invalido';
    }

    /**
     * Verifica si el RFC es de una Persona Física
     *
     * @param string $rfc
     * @return bool
     */
    public static function isPersonaFisica(string $rfc): bool
    {
        return self::detectTipoPersona($rfc) === 'fisica';
    }

    /**
     * Verifica si el RFC es de una Persona Moral
     *
     * @param string $rfc
     * @return bool
     */
    public static function isPersonaMoral(string $rfc): bool
    {
        return self::detectTipoPersona($rfc) === 'moral';
    }

    /**
     * Verifica si el RFC es genérico (XAXX010101000 o XEXX010101000)
     *
     * @param string $rfc
     * @return bool
     */
    public static function isRfcGenerico(string $rfc): bool
    {
        $tipo = self::detectTipoPersona($rfc);
        return $tipo === 'generico' || $tipo === 'extranjero';
    }

    /**
     * Valida el formato del RFC según reglas del SAT
     *
     * Persona Física: 4 letras + 6 dígitos + 3 caracteres alfanuméricos
     * Persona Moral: 3 letras + 6 dígitos + 3 caracteres alfanuméricos
     *
     * @param string $rfc
     * @return bool
     */
    public static function validateRfcFormat(string $rfc): bool
    {
        $rfc = self::cleanRfc($rfc);

        // Patrón para Persona Física (13 caracteres) o Persona Moral (12 caracteres)
        $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/';

        return preg_match($pattern, $rfc) === 1;
    }

    /**
     * Valida que el cliente tenga todos los datos requeridos para CFDI
     *
     * @param Cliente $cliente
     * @return array Array de mensajes de error (vacío si es válido)
     */
    public static function validateForCfdi(Cliente $cliente): array
    {
        $errors = [];

        // Validar RFC
        if (empty($cliente->cifnif)) {
            $errors[] = 'El cliente no tiene RFC registrado';
        } elseif (!self::validateRfcFormat($cliente->cifnif)) {
            $errors[] = 'El RFC del cliente tiene un formato inválido';
        }

        // Validar Código Postal (Domicilio Fiscal)
        if (empty($cliente->domicilioFiscal())) {
            $errors[] = 'El cliente no tiene código postal del domicilio fiscal';
        }

        // Validar Régimen Fiscal
        if (empty($cliente->regimenFiscal())) {
            $errors[] = 'El cliente no tiene régimen fiscal asignado';
        }

        // Validar Uso de CFDI
        if (empty($cliente->usoCfdi())) {
            $errors[] = 'El cliente no tiene uso de CFDI asignado';
        }

        return $errors;
    }

    /**
     * Verifica si el cliente cumple con todos los requisitos para CFDI
     *
     * @param Cliente $cliente
     * @return bool
     */
    public static function isValidForCfdi(Cliente $cliente): bool
    {
        return empty(self::validateForCfdi($cliente));
    }

    /**
     * Obtiene información del tipo de persona del cliente
     *
     * @param Cliente $cliente
     * @return array ['tipo' => string, 'descripcion' => string, 'valido' => bool]
     */
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

    /**
     * Valida la coherencia entre el RFC y el nombre del cliente
     * Las primeras letras del RFC deben corresponder al nombre/razón social
     *
     * @param Cliente $cliente
     * @return bool
     */
    public static function validateRfcNameCoherence(Cliente $cliente): bool
    {
        if (self::isRfcGenerico($cliente->cifnif)) {
            return true; // Los RFC genéricos no requieren coherencia de nombre
        }

        $rfc = self::cleanRfc($cliente->cifnif);
        $nombre = strtoupper(trim($cliente->nombre, " \t\n\r\0\x0B.,"));

        if (empty($rfc) || empty($nombre)) {
            return false;
        }

        // Extraer las letras iniciales del RFC (primeras 3 o 4 letras)
        $tipo = self::detectTipoPersona($rfc);
        $letrasRfc = substr($rfc, 0, $tipo === 'moral' ? 3 : 4);

        // Verificar que al menos la primera letra coincida
        return $letrasRfc[0] === $nombre[0];
    }

    public static function cleanFiscalString(string $string): string
    {
        return strtoupper(trim($string, " \t\n\r\0\x0B.,"));
    }
}
