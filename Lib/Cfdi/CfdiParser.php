<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi;

use CfdiUtils\Cfdi;
use CfdiUtils\ConsultaCfdiSat\RequestParameters;
use CfdiUtils\Nodes\XmlNodeUtils;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiValidationException;

final class CfdiParser
{
    private Cfdi $cfdi;
    private object $comprobante;

    public function __construct(string $xml)
    {
        if ($xml === '') {
            throw new CfdiValidationException('XML inválido.');
        }

        try {
            $this->cfdi = Cfdi::newFromString($xml);
            $this->comprobante = $this->cfdi->getQuickReader();
        } catch (\Throwable $e) {
            throw new CfdiValidationException('XML inválido.', 0, $e);
        }

        if ($this->attribute($this->comprobante, 'Version') === null) {
            throw new CfdiValidationException('El XML no contiene un Comprobante CFDI válido.');
        }
    }

    public function parse(): CfdiData
    {
        $stamp = $this->stamp();

        return new CfdiData(
            $this->required($this->comprobante, 'Version'),
            $this->attribute($stamp, 'UUID'),
            $this->required($this->comprobante, 'TipoDeComprobante'),
            $this->attribute($this->comprobante, 'Serie'),
            $this->attribute($this->comprobante, 'Folio'),
            $this->required($this->comprobante, 'Fecha'),
            $this->attribute($stamp, 'FechaTimbrado'),
            $this->required($this->comprobante, 'Moneda'),
            $this->attribute($this->comprobante, 'TipoCambio'),
            $this->attribute($this->comprobante, 'FormaPago'),
            $this->attribute($this->comprobante, 'MetodoPago'),
            $this->attribute($this->comprobante, 'LugarExpedicion'),
            $this->attribute($this->comprobante, 'Exportacion'),
            $this->required($this->comprobante, 'SubTotal'),
            $this->attribute($this->comprobante, 'Descuento'),
            $this->required($this->comprobante, 'Total'),
            $this->party($this->comprobante->emisor, false),
            $this->party($this->comprobante->receptor, true),
            $this->concepts(),
            $this->taxes($this->comprobante->impuestos),
            $this->relations(),
            $this->attribute($stamp, 'RfcProvCertif'),
            $this->attribute($this->comprobante, 'NoCertificado'),
            $this->attribute($stamp, 'NoCertificadoSAT'),
            $this->attribute($stamp, 'SelloCFD'),
            $this->attribute($stamp, 'SelloSAT'),
            $this->timbreXml($stamp),
            $this->satQuery($stamp),
            $this->addendaObservaciones()
        );
    }

    private function addendaObservaciones(): ?string
    {
        return $this->attribute($this->comprobante->addenda->observacion ?? null, 'Detalle');
    }

    private function attribute(?object $node, string $name): ?string
    {
        if ($node === null) {
            return null;
        }

        $value = (string) ($node[$name] ?? '');
        return $value === '' ? null : $value;
    }

    private function timbreXml(?object $stamp): ?string
    {
        return $stamp === null ? null : XmlNodeUtils::nodeToXmlString($stamp);
    }

    /** @return array<int, array<string, mixed>> */
    private function concepts(): array
    {
        $concepts = [];
        foreach (($this->comprobante->conceptos)() as $concept) {
            $taxes = $this->taxes($concept->impuestos);
            $concepts[] = [
                'ClaveProdServ' => $this->required($concept, 'ClaveProdServ'),
                'NoIdentificacion' => $this->attribute($concept, 'NoIdentificacion'),
                'Cantidad' => $this->required($concept, 'Cantidad'),
                'ClaveUnidad' => $this->attribute($concept, 'ClaveUnidad'),
                'Unidad' => $this->attribute($concept, 'Unidad'),
                'Descripcion' => $this->required($concept, 'Descripcion'),
                'ValorUnitario' => $this->required($concept, 'ValorUnitario'),
                'Importe' => $this->required($concept, 'Importe'),
                'Descuento' => $this->attribute($concept, 'Descuento'),
                'ObjetoImp' => $this->attribute($concept, 'ObjetoImp'),
                'Traslados' => $taxes['traslados'],
                'Retenciones' => $taxes['retenciones'],
            ];
        }

        return $concepts;
    }

    private function party(object $node, bool $receiver): array
    {
        return [
            'rfc' => $this->required($node, 'Rfc'),
            'nombre' => $this->attribute($node, 'Nombre'),
            'regimenFiscal' => $this->attribute($node, 'RegimenFiscal'),
            'domicilioFiscal' => $receiver ? $this->attribute($node, 'DomicilioFiscalReceptor') : null,
            'usoCfdi' => $receiver ? $this->attribute($node, 'UsoCFDI') : null,
            'residenciaFiscal' => $receiver ? $this->attribute($node, 'ResidenciaFiscal') : null,
            'numRegIdTrib' => $receiver ? $this->attribute($node, 'NumRegIdTrib') : null,
        ];
    }

    /** @return array<int, array{tiporelacion: ?string, relacionados: array<int, string>}> */
    private function relations(): array
    {
        $relations = [];
        foreach (($this->comprobante)('CfdiRelacionados') as $group) {
            $uuids = [];
            foreach (($group)('CfdiRelacionado') as $related) {
                $uuid = $this->attribute($related, 'UUID');
                if ($uuid !== null) {
                    $uuids[] = $uuid;
                }
            }

            if ($uuids !== []) {
                $relations[] = ['tiporelacion' => $this->attribute($group, 'TipoRelacion'), 'relacionados' => $uuids];
            }
        }

        return $relations;
    }

    private function required(object $node, string $name): string
    {
        $value = $this->attribute($node, $name);
        if ($value === null) {
            throw new CfdiValidationException('Falta atributo CFDI requerido: ' . $name . '.');
        }

        return $value;
    }

    private function satQuery(?object $stamp): ?string
    {
        return $stamp === null ? null : RequestParameters::createFromCfdi($this->cfdi)->expression();
    }

    private function stamp(): ?object
    {
        $stamp = $this->cfdi->getNode()->searchNode('cfdi:Complemento', 'tfd:TimbreFiscalDigital');
        return is_object($stamp) ? $stamp : null;
    }

    private function taxes(?object $node): array
    {
        if ($node === null) {
            return ['traslados' => [], 'retenciones' => [], 'totalTrasladados' => null, 'totalRetenidos' => null];
        }

        return [
            'traslados' => $this->taxItems($node->traslados ?? null),
            'retenciones' => $this->taxItems($node->retenciones ?? null),
            'totalTrasladados' => $this->attribute($node, 'TotalImpuestosTrasladados'),
            'totalRetenidos' => $this->attribute($node, 'TotalImpuestosRetenidos'),
        ];
    }

    /** @return array<int, array{Base: ?string, Impuesto: ?string, TipoFactor: ?string, TasaOCuota: ?string, Importe: ?string}> */
    private function taxItems(?object $node): array
    {
        if ($node === null) {
            return [];
        }

        $taxes = [];
        foreach (($node)() as $tax) {
            $taxes[] = [
                'Base' => $this->attribute($tax, 'Base'),
                'Impuesto' => $this->attribute($tax, 'Impuesto'),
                'TipoFactor' => $this->attribute($tax, 'TipoFactor'),
                'TasaOCuota' => $this->attribute($tax, 'TasaOCuota'),
                'Importe' => $this->attribute($tax, 'Importe'),
            ];
        }

        return $taxes;
    }
}
