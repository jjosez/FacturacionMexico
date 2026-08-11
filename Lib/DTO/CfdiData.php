<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\DTO;

final class CfdiData
{
    /** @param array<int, array<string, mixed>> $conceptos */
    /** @param array{traslados: array, retenciones: array, totalTrasladados: ?string, totalRetenidos: ?string} $impuestos */
    /** @param array<int, array{tiporelacion: ?string, relacionados: array<int, string>}> $relacionados */
    public function __construct(
        public string $version,
        public ?string $uuid,
        public string $tipoComprobante,
        public ?string $serie,
        public ?string $folio,
        public string $fecha,
        public ?string $fechaTimbrado,
        public string $moneda,
        public ?string $tipoCambio,
        public ?string $formaPago,
        public ?string $metodoPago,
        public ?string $lugarExpedicion,
        public ?string $exportacion,
        public string $subtotal,
        public ?string $descuento,
        public string $total,
        public string $emisorRfc,
        public ?string $emisorNombre,
        public ?string $emisorRegimenFiscal,
        public string $receptorRfc,
        public ?string $receptorNombre,
        public ?string $receptorDomicilioFiscal,
        public ?string $receptorRegimenFiscal,
        public ?string $receptorUsoCfdi,
        public ?string $receptorResidenciaFiscal,
        public ?string $receptorNumRegIdTrib,
        public array $conceptos,
        public array $impuestos,
        public array $relacionados,
        public ?string $rfcProvCertif,
        public ?string $noCertificado,
        public ?string $noCertificadoSat,
        public ?string $selloCfd,
        public ?string $selloSat,
        public ?string $addendaObservaciones
    ) {
    }
}
