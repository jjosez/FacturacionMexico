<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019-2025 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\CfdiRepositoryInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\StampProviderInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiConfigurationException;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\StampProviders\FinkokStampProvider;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\CfdiDatabaseStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\CfdiFileStorage;

/**
 * Factory para crear instancias de servicios CFDI configurados
 * Responsabilidad: Centralizar la creación de servicios con configuración y validación
 */
class CfdiServiceFactory
{
    /**
     * Valida la configuración del PAC (Proveedor Autorizado de Certificación)
     *
     * @param Empresa $company
     * @return array Array vacío si todo está correcto, o array con configuraciones faltantes
     */
    private static function validateStampProviderConfiguration(Empresa $company): array
    {
        $missing = [];

        $pacCredentials = CfdiSettings::pacCredentials($company);

        if (empty($pacCredentials['user'])) {
            $missing['stamp-user'] = 'Usuario del PAC (Finkok)';
        }

        if (empty($pacCredentials['token'])) {
            $missing['stamp-token'] = 'Token/Contraseña del PAC (Finkok)';
        }

        return $missing;
    }

    /**
     * Valida la configuración de certificados SAT
     *
     * @param Empresa $company
     * @return array Array vacío si todo está correcto, o array con configuraciones faltantes
     */
    private static function validateSatCredentials(Empresa $company): array
    {
        $missing = [];

        try {
            $credentials = CfdiSettings::satCredentials($company);

            if (empty($credentials['certificado']) || !file_exists($credentials['certificado'])) {
                $missing['certificado'] = 'Certificado SAT (.cer) no configurado o no existe';
            }

            if (empty($credentials['llave']) || !file_exists($credentials['llave'])) {
                $missing['llave'] = 'Llave privada SAT (.key) no configurada o no existe';
            }

            if (empty($credentials['secreto'])) {
                $missing['secreto'] = 'Contraseña de la llave privada SAT';
            }
        } catch (\Exception $e) {
            $missing['sat-general'] = 'Credenciales SAT no configuradas correctamente';
        }

        return $missing;
    }

    /**
     * Crea el proveedor de timbrado configurado
     *
     * @param Empresa $company
     * @return StampProviderInterface
     * @throws CfdiConfigurationException Si falta configuración requerida
     */
    public static function createStampProvider(Empresa $company): StampProviderInterface
    {
        // Validar configuración del PAC
        $missingPac = self::validateStampProviderConfiguration($company);
        if (!empty($missingPac)) {
            throw new CfdiConfigurationException(
                'Configuración del PAC incompleta',
                $missingPac
            );
        }

        // Validar certificados SAT
        $missingSat = self::validateSatCredentials($company);
        if (!empty($missingSat)) {
            throw new CfdiConfigurationException(
                'Configuración de certificados SAT incompleta',
                $missingSat
            );
        }

        $pacCredentials = CfdiSettings::pacCredentials($company);

        try {
            return new FinkokStampProvider(
                $pacCredentials['user'],
                $pacCredentials['token'],
                $pacCredentials['test_mode']
            );
        } catch (\Exception $e) {
            throw new CfdiConfigurationException(
                'Error al inicializar el servicio de timbrado: ' . $e->getMessage()
            );
        }
    }

    /**
     * Crea el servicio de almacenamiento configurado
     *
     * @return CfdiRepositoryInterface
     */
    public static function createStorageProvider(): CfdiRepositoryInterface
    {
        return match (CfdiSettings::storageType()) {
            'database' => new CfdiDatabaseStorage(),
            'file' => new CfdiFileStorage(),
            default => new CfdiFileStorage(),
        };
    }

    /**
     * Crea el servicio principal de CFDI
     *
     * @param Empresa $company
     * @return CfdiService
     */
    public static function createCfdiService(Empresa $company): CfdiService
    {
        return new CfdiService(
            self::createStampProvider($company),
            self::createStorageProvider()
        );
    }
}
