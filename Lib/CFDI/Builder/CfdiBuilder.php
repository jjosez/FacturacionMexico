<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;

use CfdiUtils\Certificado\Certificado;
use CfdiUtils\CfdiCreator40;
use CfdiUtils\Elements\Cfdi40\Comprobante;
use CfdiUtils\XmlResolver\XmlResolver;
use Exception;
use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

abstract class CfdiBuilder
{
    const RFC_EXTRANJERO = 'XEXX010101000';

    /**
     * @var Comprobante
     */
    protected $comprobante;

    /**
     * @var CfdiCreator40
     */
    protected $creator;

    /**
     * @var Empresa
     */
    protected $empresa;

    /**
     * @var FacturaCliente
     */
    protected $factura;

    /**
     * @var string
     */
    protected $llaveprivada;

    /**
     * @var
     */
    protected $certificado;

    /**
     * @var string
     */
    protected $secreto;

    /**
     * @var string
     */
    protected $tipoComprobante;

    public function __construct(FacturaCliente $factura, string $tipo)
    {
        $this->empresa = $factura->getCompany();
        $this->factura = $factura;
        $this->tipoComprobante = $tipo;
        $this->inicializaComprobante();
    }

    abstract protected function getAtributosComprobante();

    protected function getFechaFactura($f, $h): string
    {
        return date("Y-m-d", strtotime($f)) . 'T' . date("H:i:s", strtotime($h));
    }

    protected function getIvaFromValue($base, $iva, $round = 2): float
    {
        return $base * $iva / 100;
    }

    protected function getAbsoluteValue($val)
    {
        if ($val < 0) $val = -$val;

        return $val;
    }

    protected function getTasaValue($iva): string
    {
        return number_format($iva / 100, 6);
    }

    abstract protected function setConceptos();

    /**
     * @throws Exception
     */
    protected function setReceptor(): void
    {
        $fiscalID = $this->factura->cifnif;
        $customer = $this->factura->getSubject();

        if ($customer->cifnif !== $fiscalID) {
            throw new Exception('El ID Fiscal del cliente ' . $fiscalID
                . 'no coincide con el de la Factura ' . $this->factura->cifnif
            );
        }

        if ($customer->tipoidfiscal === 'RFC') {
            $receptor = [
                'Rfc' => $customer->cifnif,
                'Nombre' => $customer->razonsocial,
                'UsoCFDI' => $customer->usocfdi,
                'RegimenFiscalReceptor' => $customer->regimenfiscal,
                'DomicilioFiscalReceptor' => '97780',
            ];
        } else {
            $receptor = [
                'Rfc' => self::RFC_EXTRANJERO,
                'Nombre' => $customer->razonsocial,
                'UsoCFDI' => 'P01',
                'NumRegIdTrib' => $customer->cifnif,
                'ResidenciaFiscal' => $this->factura->codpais
            ];
        }

        $this->comprobante->addReceptor($receptor);
    }

    protected function setEmisor(): void
    {
        $regimen = AppSettings::get('cfdi', 'regimen');
        $emisor = [
            'RegimenFiscal' => $regimen,
        ];

        $this->comprobante->addEmisor($emisor);
    }

    protected function setSello(): void
    {
        $this->creator->addSello($this->llaveprivada, $this->secreto);
    }

    private function inicializaComprobante(): void
    {
        $atributos = $this->getAtributosComprobante();
        $resolver = new XmlResolver(CFDI_XSLT_DIR);

        $this->creator = new CfdiCreator40($atributos);
        $this->creator->setXmlResolver($resolver);

        $this->comprobante = $this->creator->comprobante();
    }

    /**
     * @throws Exception
     */
    protected function buildComprobante(): void
    {
        $this->setReceptor();
        $this->setEmisor();
        $this->setConceptos();
        $this->setSello();
    }

    /**
     * @throws Exception
     */
    public function getXml(): string
    {
        $this->buildComprobante();

        return $this->creator->asXml();
    }

    /**
     * @throws Exception
     */
    public function getValidacion(): void
    {
        $this->buildComprobante();
        $asserts = $this->creator->validate();

        foreach ($asserts as $assert) {
            echo $assert, PHP_EOL;
        }
    }

    public function setDocumentosRelacionados(array $foliosfiascales, string $tiporelacion): void
    {
        if (empty($foliosfiascales)) {
            return;
        }

        foreach ($foliosfiascales as $folio) {
            $this->comprobante->addCfdiRelacionados([
                'TipoRelacion' => $tiporelacion
            ])->addCfdiRelacionado([
                'UUID' => $folio
            ]);
        }

        /*$this->comprobante->getCfdiRelacionados()->addAttributes([
            'TipoRelacion' => $tiporelacion
        ]);*/
    }

    protected function buildConceptoTraslado($linea): array
    {
        $traslado = [];
        $tipoFactor = ($linea->iva > 0) ? 'Tasa' : 'Exento';

        $traslado['Base'] = $linea->pvptotal;
        $traslado['TipoFactor'] = $tipoFactor;

        if ($tipoFactor === 'Exento') {
            $traslado['Impuesto'] = '002';
        } else {
            $traslado['Impuesto'] = $linea->codimpuesto;
            $traslado['TasaOCuota'] = $this->getTasaValue($linea->iva);
            $traslado['Importe'] = $this->getIvaFromValue($linea->pvptotal, $linea->iva);
        }

        return $traslado;
    }

    protected function tasaCambio(string $moneda, float $tasa): float
    {
        if ('MXN' == $moneda) {
            return 1;
        }

        return $tasa;
    }

    public function setCertificado(string $filename): void
    {
        $certificado = new Certificado($filename);
        $this->creator->putCertificado($certificado);
        //$this->certificado = new Certificado($filename);
    }

    public function setLlavePrivada(string $llaveprivada, string $secreto): void
    {

        //$this->llaveprivada = file_get_contents($filename) ?: '';
        $this->llaveprivada = $llaveprivada;
        $this->secreto = $secreto;
    }
}
