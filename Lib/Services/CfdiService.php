<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Services;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\CFDI\CfdiSettings;;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Contract\StampProviderInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiFactory;

class CfdiService
{
    private StampProviderInterface $stampProvider;
    private CfdiStorage $storage;


    public function __construct(StampProviderInterface $provider, CfdiStorage $storage)
    {
        $this->stampProvider = $provider;
        $this->storage = $storage;
    }

    public function stampInvoice(FacturaCliente $invoice, CfdiCliente $cfdi, array $relation = []): ?string
    {
        if ($this->isGlobal($invoice)) {
            $xml = CfdiFactory::buildCfdiGlobal($invoice);
        } elseif ($this->isEgreso($invoice)) {
            if (empty($relation['relacionados'])) {
                Tools::log()->warning('No se especificaron CFDIs relacionados para egreso.');
                return null;
            }
            $xml = CfdiFactory::buildCfdiEgreso($invoice, $relation);
        } else {
            $xml = CfdiFactory::buildCfdiIngreso($invoice, $relation);
        }

        $response = $this->stampProvider->stamp($xml);

        if ($response->hasError()) {
            Tools::log()->warning("Error al timbrar: {$response->getMessage()}");
            if ($response->hasPreviousSign()) {
                Tools::log()->notice('Obtenido timbre previo.');
                $response = $this->pac->getTimbradoPrevio($xml);

                if ($response->hasError()) {
                    return null;
                }
            } else {
                return null;
            }
        }

        $xmlTimbrado = $response->getXml();

        // Guardamos el XML en disco y actualizamos modelos
        if ($this->storage->saveCfdi($invoice, $cfdi, $xmlTimbrado)) {
            $this->storage->saveCfdiXml($cfdi, $xmlTimbrado);
        }

        // Cambiamos el estado de la factura
        $invoice->idestado = CfdiSettings::stampedInvoiceStatus();
        $invoice->save();

        return $xmlTimbrado;
    }

    public function cancelInvoice(CfdiCliente $cfdi): bool
    {
        $credentials = CfdiSettings::satCredentials();
        $result = $this->pac->cancelar($cfdi->uuid, $credentials);

        if ($result) {
            Tools::log()->notice("CFDI cancelado correctamente.");
            $this->storage->updateCfdiStatus($cfdi, 'Cancelado');
            return true;
        }

        Tools::log()->error("Error al cancelar CFDI.");
        return false;
    }

    public function invoiceStatus(CfdiCliente $cfdi, string $rfcEmisor): void
    {
        $query = [
            'uuid' => $cfdi->uuid,
            'receptor' => $cfdi->rfcreceptor,
            'total' => $cfdi->total,
            'emisor' => $rfcEmisor
        ];

        $status = $this->pac->getSatStatus($query);

        Tools::log()->notice("Estatus SAT: " . $status->query());
        Tools::log()->notice("Cancelable: " . $status->cancellable());
        Tools::log()->notice("Cancelación: " . $status->cancellation());
    }

    private function isEgreso(FacturaCliente $factura): bool
    {
        return CfdiSettings::serieEgreso() === $factura->codserie;
    }

    private function isGlobal(FacturaCliente $factura): bool
    {
        return CfdiSettings::rfcGenerico() === $factura->cifnif;
    }
}
