<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Services;

use CfdiUtils\Cfdi;
use CfdiUtils\ConsultaCfdiSat\RequestParameters;
use CfdiUtils\Nodes\XmlNodeUtils;
use CfdiUtils\TimbreFiscalDigital\TfdCadenaDeOrigen;
use CfdiUtils\XmlResolver\XmlResolver;
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

    /*public function cfdiRelacionados(): array
    {
        $result = [];
        $relacionados = $this->comprobante->cfdirelacionados;
        $result['tiporelacion'] = $relacionados['TipoRelacion'];

        foreach ($relacionados() as $relacionado) {
            $result['relacionados'] = ['uuid' => $relacionado['UUID']];
        }

        return $result;
    }*/
    public function cfdiRelacionados(): array
    {
        $result = [];

        $comprobante = $this->comprobante->comprobante();
        foreach ($comprobante->searchNodes('cfdi:CfdiRelacionados') as $relacionadosNode) {
            $tipoRelacion = $relacionadosNode->getAttribute('TipoRelacion');
            $relacionados = [];

            foreach ($relacionadosNode->searchNodes('cfdi:CfdiRelacionado') as $relacionadoNode) {
                $uuid = $relacionadoNode->getAttribute('UUID');
                if ($uuid) {
                    $relacionados[] = $uuid;
                }
            }

            if (!empty($relacionados)) {
                $result[] = [
                    'tiporelacion' => $tipoRelacion,
                    'relacionados' => $relacionados
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
