<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;

use CfdiUtils\Certificado\Certificado;
use CfdiUtils\CfdiCreator33;
use CfdiUtils\XmlResolver\XmlResolver;
use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

abstract class CfdiBuilder
{
    protected $comprobante;
    protected $creator;
    protected $empresa;
    protected $factura;
    protected $tipo;
    protected $uso;

    public function __construct(FacturaCliente $factura, Empresa $empresa, string $tipo, string $uso)
    {
        if (null === $factura) {
            throw new \Error('Parametros incorrectos');
        }

        $this->empresa = $empresa;
        $this->factura = $factura;
        $this->tipo = $tipo;
        $this->uso = $uso;
        $this->inicializaComprobante();
    }

    abstract protected function getAtributosComprobante();
    abstract protected function setConceptos();

    protected function getFechaFactura($f, $h)
    {
        return date("Y-m-d", strtotime($f)) . 'T' . date("H:i:s", strtotime($h));
    }

    protected function getIvaFromValue($base, $iva, $round = 2)
    {
        return $base * $iva / 100;
    }

    protected function getTasaValue($iva)
    {
        return number_format($iva / 100, 6);
    }

    protected function setDatosCliente()
    {
        $receptor = [
            'Rfc' => $this->factura->cifnif,
            'UsoCFDI' => $this->uso,
        ];

        $this->comprobante->addReceptor($receptor);
    }

    protected function setDatosEmpresa()
    {
        $regimen = AppSettings::get('cfdi', 'regimen');
        $emisor = [
            'RegimenFiscal' => $regimen,
        ];

        $this->comprobante->addEmisor($emisor);
    }

    protected function setSello()
    {
        $filename = CFDI_CERT_DIR . DIRECTORY_SEPARATOR . 'CSD01_AAA010101AAA.key';
        $llave = file_get_contents($filename) ?: '';

        $this->creator->addSello($llave, '1234567a');
    }

    private function inicializaComprobante()
    {
        $atributos = $this->getAtributosComprobante();

        $certificado = new Certificado(CFDI_CERT_DIR . DIRECTORY_SEPARATOR . 'CSD01_AAA010101AAA.cer');
        $resolver = new XmlResolver(CFDI_XSLT_DIR);

        $this->creator = new CfdiCreator33($atributos, $certificado);
        $this->creator->setXmlResolver($resolver);

        $this->comprobante = $this->creator->comprobante();
    }

    private function buildComprobante()
    {
        $this->setDatosCliente();
        $this->setDatosEmpresa();
        $this->setConceptos();
        $this->setSello();
    }

    public function getXml()
    {
        $this->buildComprobante();

        return $this->creator->asXml();
    }

    public function getValidacion()
    {
        $this->buildComprobante();
        $asserts = $this->creator->validate();

        foreach ($asserts as $assert) {
            echo $assert, PHP_EOL;
        }
    }
}
