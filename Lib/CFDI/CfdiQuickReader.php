<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use CfdiUtils\Cfdi;
use CfdiUtils\ConsultaCfdiSat\RequestParameters;
use CfdiUtils\Nodes\XmlNodeUtils;
use CfdiUtils\TimbreFiscalDigital\TfdCadenaDeOrigen;
use CfdiUtils\XmlResolver\XmlResolver;
use InvalidArgumentException;
use NumerosEnLetras;

class CfdiQuickReader
{
    private $cfdi;
    private $comprobante;

    public function __construct($xml)
    {
        if (empty($xml)) {
            throw new InvalidArgumentException('XML invalido');
        }

        $this->cfdi = Cfdi::newFromString($xml);
        $this->comprobante = $this->cfdi->getQuickReader();
    }

    public function cadenaOrigen()
    {
        $tfd = $this->cfdi->getNode()->searchNode('cfdi:Complemento', 'tfd:TimbreFiscalDigital');
        $tfdXmlString = XmlNodeUtils::nodeToXmlString($tfd);

        $builder = new TfdCadenaDeOrigen();
        $resolver = new XmlResolver(CFDI_XSLT_DIR);

        $builder->setXmlResolver($resolver);
        return $builder->build($tfdXmlString);
    }

    public function cfdiRelacionados()
    {
        $result = [];
        $relacionados = $this->comprobante->cfdirelacionados;
        $result['tiporelacion'] = $relacionados['TipoRelacion'];

        foreach ($relacionados() as $relacionado)
        {
            $result['relacionados'] = ['uuid' => $relacionado['UUID']];
        }

        return $result;
    }

    public function noCertificado()
    {
        return $this->comprobante['NoCertificado'];
    }
    public function noCertificadoSAT()
    {
        return $this->comprobante->complemento->timbreFiscalDigital['NoCertificadoSAT'];
    }

    public function conceptos()
    {
        $conceptos = $this->comprobante->conceptos;

        return $conceptos();
    }

    public function conceptosData()
    {
        $result = [];

        foreach ($this->conceptos() as $concepto)
        {
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

    public function conceptosTraslados($concepto)
    {
        $result = [];

        foreach(($concepto->impuestos->traslados)() as $traslado) {
            $result[] = [
                'impuesto' => $traslado['impuesto'],
                'tasa' => $traslado['tasaocuota'],
                'importe' => $traslado['importe'],
            ];
        }

        return $result;
    }

    public function emisorNombre()
    {
        return $this->comprobante->emisor['Nombre'];
    }

    public function emisorRfc()
    {
        return $this->comprobante->emisor['Rfc'];
    }

    public function emisorRegimenFiscal()
    {
        return (new CfdiCatalogo())->regimenFiscal()->getDescripcion(
            $this->comprobante->emisor['RegimenFiscal']
        );
    }

    public function folio()
    {
        return $this->comprobante['Folio'];
    }

    public function fechaExpedicion()
    {
        return $this->comprobante['Fecha'];
    }

    public function fechaTimbrado()
    {
        return $this->comprobante->complemento->timbreFiscalDigital['FechaTimbrado'];
    }

    public function formaPago()
    {
        return $this->comprobante['FormaPago'];
    }

    public function lugarExpedicion()
    {
        return $this->comprobante['LugarExpedicion'];
    }

    public function metodoPago()
    {
        return $this->comprobante['MetodoPago'];
    }

    public function moneda()
    {
        return $this->comprobante['Moneda'];
    }

    public function proveedorCertif()
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

    public function totalImpuestosTrasladados()
    {
        return $this->comprobante->impuestos['TotalImpuestosTrasladados'];
    }

    public function total()
    {
        return $this->comprobante['Total'];
    }

    public function totalLetra()
    {
        return NumerosEnLetras::convertir($this->total(), $this->comprobante['Moneda'], true);
    }

    public function uuid()
    {
        return $this->comprobante->complemento->timbreFiscalDigital['UUID'];
    }

    public function version()
    {
        return $this->comprobante['Version'];
    }
}
