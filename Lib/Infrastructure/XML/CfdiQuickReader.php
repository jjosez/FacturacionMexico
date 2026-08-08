<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML;

use CfdiUtils\Cfdi;
use CfdiUtils\ConsultaCfdiSat\RequestParameters;
use CfdiUtils\Nodes\XmlNodeUtils;
use CfdiUtils\TimbreFiscalDigital\TfdCadenaDeOrigen;
use CfdiUtils\XmlResolver\XmlResolver;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiCatalogo;
use InvalidArgumentException;
use Luecano\NumeroALetras\NumeroALetras;

class CfdiQuickReader
{
    private $cfdi;
    private $comprobante;
    private array $cache = [];

    public function __construct(string $xml)
    {
        if (empty($xml)) {
            throw new InvalidArgumentException('XML invalido');
        }

        $this->cfdi = Cfdi::newFromString($xml);
        $this->comprobante = $this->cfdi->getQuickReader();
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    public function cadenaOrigen(): string
    {
        $tfd = $this->cfdi->getNode()->searchNode('cfdi:Complemento', 'tfd:TimbreFiscalDigital');
        $tfdXmlString = XmlNodeUtils::nodeToXmlString($tfd);

        $builder = new TfdCadenaDeOrigen();
        $resolver = new XmlResolver(CFDI_XSLT_DIR);

        $builder->setXmlResolver($resolver);
        return $builder->build($tfdXmlString);
    }

    public function relacionados(): array
    {
        $result = [];

        $relacionadosNodes = ($this->comprobante)('CfdiRelacionados');

        foreach ($relacionadosNodes as $relacionadoNode) {
            $uuids = [];

            $children = ($relacionadoNode)('CfdiRelacionado');
            foreach ($children as $child) {
                $uuid = $child['UUID'];
                if ($uuid) {
                    $uuids[] = $uuid;
                }
            }

            if (!empty($uuids)) {
                $result[] = [
                    'tiporelacion' => $relacionadoNode['TipoRelacion'],
                    'relacionados' => $uuids
                ];
            }
        }

        return $result;
    }

    public function noCertificado(): string
    {
        return $this->comprobante['NoCertificado'];
    }

    public function noCertificadoSAT(): string
    {
        return $this->comprobante->complemento->timbreFiscalDigital['NoCertificadoSAT'];
    }

    public function addendaObservaciones(): string
    {
        return $this->comprobante->addenda->observacion['Detalle'];
    }

    public function getAddendas(): array
    {
        if (isset($this->cache['addendas'])) {
            return $this->cache['addendas'];
        }

        $addendas = [];
        $addendaNode = $this->comprobante->addenda;

        if ($addendaNode && count($addendaNode) > 0) {
            foreach ($addendaNode as $addendaName => $addendaContent) {
                $addendas[$addendaName] = $this->extractAddendaFields($addendaContent);
            }
        }

        $this->cache['addendas'] = $addendas;
        return $addendas;
    }

    private function extractAddendaFields($node): array
    {
        $fields = [];
        foreach ($node as $key => $value) {
            if (is_object($value) && count($value) > 0) {
                $fields[$key] = $this->extractAddendaFields($value);
            } else {
                $fields[$key] = (string) $value;
            }
        }
        return $fields;
    }

    public function getImpuestos(): array
    {
        if (isset($this->cache['impuestos'])) {
            return $this->cache['impuestos'];
        }

        $traslados = [];
        $retenciones = [];

        if (isset($this->comprobante->impuestos)) {
            $impuestosNode = $this->comprobante->impuestos;

            if (isset($impuestosNode->retenciones)) {
                foreach (($impuestosNode->retenciones)() as $retencion) {
                    $retenciones[] = [
                        'Impuesto' => $retencion['Impuesto'],
                        'Importe' => $retencion['Importe'],
                    ];
                }
            }

            if (isset($impuestosNode->traslados)) {
                foreach (($impuestosNode->traslados)() as $traslado) {
                    $traslados[] = [
                        'Impuesto' => $traslado['Impuesto'],
                        'Base' => $traslado['Base'],
                        'TipoFactor' => $traslado['TipoFactor'],
                        'TasaOCuota' => $traslado['TasaOCuota'],
                        'Importe' => $traslado['Importe'],
                    ];
                }
            }
        }

        $result = [
            'traslados' => $traslados,
            'retenciones' => $retenciones,
            'totalTrasladados' => $this->comprobante->impuestos['TotalImpuestosTrasladados'] ?? '0',
            'totalRetenidos' => $this->comprobante->impuestos['TotalImpuestosRetenidos'] ?? '0',
        ];

        $this->cache['impuestos'] = $result;
        return $result;
    }

    public function getConceptos(): array
    {
        if (isset($this->cache['conceptos'])) {
            return $this->cache['conceptos'];
        }

        $out = [];

        foreach (($this->comprobante->conceptos)() as $concepto) {
            $traslados = [];
            $retenciones = [];

            if (isset($concepto->impuestos)) {
                if (isset($concepto->impuestos->traslados)) {
                    foreach (($concepto->impuestos->traslados)() as $traslado) {
                        $traslados[] = [
                            'Impuesto' => $traslado['Impuesto'],
                            'Base' => $traslado['Base'],
                            'TipoFactor' => $traslado['TipoFactor'],
                            'TasaOCuota' => $traslado['TasaOCuota'],
                            'Importe' => $traslado['Importe'],
                        ];
                    }
                }

                if (isset($concepto->impuestos->retenciones)) {
                    foreach (($concepto->impuestos->retenciones)() as $retencion) {
                        $retenciones[] = [
                            'Impuesto' => $retencion['Impuesto'],
                            'Importe' => $retencion['Importe'],
                        ];
                    }
                }
            }

            $out[] = [
                'ClaveProdServ' => $concepto['ClaveProdServ'],
                'NoIdentificacion' => $concepto['NoIdentificacion'],
                'Cantidad' => $concepto['Cantidad'],
                'ClaveUnidad' => $concepto['ClaveUnidad'],
                'Descripcion' => $concepto['Descripcion'],
                'ValorUnitario' => $concepto['ValorUnitario'],
                'Importe' => $concepto['Importe'],
                'Descuento' => $concepto['Descuento'],
                'Traslados' => $traslados,
                'Retenciones' => $retenciones,
            ];
        }

        $this->cache['conceptos'] = $out;
        return $out;
    }

    public function conceptosNormalized(): array
    {
        return $this->getConceptos();
    }

    public function conceptosData(): array
    {
        $result = [];

        foreach ($this->getConceptos() as $concepto) {
            $result[] = [
                'cantidad' => $concepto['Cantidad'],
                'id' => $concepto['NoIdentificacion'],
                'descripcion' => $concepto['Descripcion'],
                'clavesat' => $concepto['ClaveProdServ'],
                'claveum' => $concepto['ClaveUnidad'],
                'precio' => $concepto['ValorUnitario'],
                'importe' => $concepto['Importe']
            ];
        }

        return $result;
    }

    public function conceptosTraslados($concepto): array
    {
        $result = [];

        if (isset($concepto->impuestos) && isset($concepto->impuestos->traslados)) {
            foreach (($concepto->impuestos->traslados)() as $traslado) {
                $result[] = [
                    'impuesto' => $traslado['Impuesto'],
                    'tasa' => $traslado['TasaOCuota'],
                    'importe' => $traslado['Importe'],
                ];
            }
        }

        return $result;
    }

    public function emisorNombre(): string
    {
        return $this->comprobante->emisor['Nombre'];
    }

    public function emisorRfc(): string
    {
        return $this->comprobante->emisor['Rfc'];
    }

    public function emisorRegimenFiscal(): string
    {
        return (new CfdiCatalogo())->regimenFiscal()->getDescripcion(
            $this->comprobante->emisor['RegimenFiscal']
        );
    }

    public function folio(): string
    {
        return $this->comprobante['Folio'];
    }

    public function fechaExpedicion(): string
    {
        return $this->comprobante['Fecha'];
    }

    public function fechaTimbrado(): string
    {
        return $this->comprobante->complemento->timbreFiscalDigital['FechaTimbrado'];
    }

    public function formaPago(): string
    {
        return $this->comprobante['FormaPago'];
    }

    public function lugarExpedicion(): string
    {
        return $this->comprobante['LugarExpedicion'];
    }

    public function metodoPago(): string
    {
        return $this->comprobante['MetodoPago'];
    }

    public function moneda(): string
    {
        return $this->comprobante['Moneda'];
    }

    public function proveedorCertif(): string
    {
        return $this->comprobante->complemento->timbreFiscalDigital['RfcProvCertif'];
    }

    public function qrCodeUrl(): string
    {
        $parameters = RequestParameters::createFromCfdi($this->cfdi);
        return $parameters->expression();
    }

    public function receptorNombre(): string
    {
        return $this->comprobante->receptor['Nombre'];
    }

    public function receptorIdTrib(): string
    {
        return $this->comprobante->receptor['NumRegIdTrib'];
    }

    public function receptorRfc(): string
    {
        return $this->comprobante->receptor['Rfc'];
    }

    public function receptorUsoCfdi(): string
    {
        return (new CfdiCatalogo())->usoCfdi()->getDescripcion(
            $this->comprobante->receptor['UsoCFDI']
        );
    }

    public function selloCfd(): string
    {
        return $this->comprobante->complemento->timbreFiscalDigital['SelloCFD'];
    }

    public function selloSat(): string
    {
        return $this->comprobante->complemento->timbreFiscalDigital['SelloSat'];
    }

    public function serie(): string
    {
        return $this->comprobante['Serie'];
    }

    public function subTotal(): string
    {
        return $this->comprobante['SubTotal'];
    }

    public function tipoComprobamte(): string
    {
        return $this->comprobante['TipoDeComprobante'];
    }

    public function totalDescuentos(): string
    {
        return $this->comprobante['Descuento'];
    }

    public function totalImpuestosTrasladados(): string
    {
        return $this->comprobante->impuestos['TotalImpuestosTrasladados'];
    }

    public function total(): string
    {
        return $this->comprobante['Total'];
    }

    public function totalLetra(): string
    {
        return (new NumeroALetras())->toInvoice($this->total(), 2, $this->comprobante['Moneda']);
    }

    public function uuid(): string
    {
        return $this->comprobante->complemento->timbreFiscalDigital['UUID'];
    }

    public function version(): string
    {
        return $this->comprobante['Version'];
    }
}
