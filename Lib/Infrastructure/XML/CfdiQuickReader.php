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

    public function __construct(string $xml)
    {
        if (empty($xml)) {
            throw new InvalidArgumentException('XML invalido');
        }

        $this->cfdi = Cfdi::newFromString($xml);
        $this->comprobante = $this->cfdi->getQuickReader();
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

    public function conceptos()
    {
        $conceptos = $this->comprobante->conceptos;

        return $conceptos();
    }

    public function conceptosData(): array
    {
        $result = [];

        foreach ($this->conceptos() as $concepto) {
            $result[] = [
                'cantidad' => $concepto['cantidad'],
                'id' => $concepto['noidentificacion'],
                'descripcion' => $concepto['descripcion'],
                'clavesat' => $concepto['claveprodserv'],
                'claveum' => $concepto['claveunidad'],
                'precio' => $concepto['valorunitario'],
                'importe' => $concepto['importe']
            ];
        }

        return $result;
    }

    public function conceptosTraslados($concepto): array
    {
        $result = [];

        foreach (($concepto->impuestos->traslados)() as $traslado) {
            $result[] = [
                'impuesto' => $traslado['impuesto'],
                'tasa' => $traslado['tasaocuota'],
                'importe' => $traslado['importe'],
            ];
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
