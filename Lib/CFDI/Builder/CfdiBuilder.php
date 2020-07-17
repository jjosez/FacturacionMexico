<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;

use CfdiUtils\Certificado\Certificado;
use CfdiUtils\CfdiCreator33;
use CfdiUtils\XmlResolver\XmlResolver;
use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

abstract class CfdiBuilder
{
    const RFC_EXTRANJERO = 'XEXX010101000';

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

    protected function getAbsoluteValue($val)
    {
        if ($val < 0) $val = -$val;

        return $val;
    }

    protected function getTasaValue($iva)
    {
        return number_format($iva / 100, 6);
    }

    protected function setDatosCliente()
    {
        $fiscalID = $this->factura->cifnif;
        $customer = (new Cliente())->get($this->factura->codcliente);

        if ($customer->cifnif !== $fiscalID) {
            throw new \Exception('El ID Fiscal del cliente ' . $fiscalID
                . 'no coincide con el de la Factura ' . $this->factura->cifnif
            );
        }

        if ($customer->tipoidfiscal === 'RFC') {
            $receptor = [
                'Rfc' => $customer->cifnif,
                'Nombre' => $customer->razonsocial,
                'UsoCFDI' => $this->uso,
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
        $filename = CFDI_CERT_DIR . DIRECTORY_SEPARATOR . AppSettings::get('cfdi', 'keyfile');
        $password = AppSettings::get('cfdi', 'passphrase');
        $llave = file_get_contents($filename) ?: '';

        $this->creator->addSello($llave, $password);
    }

    private function inicializaComprobante()
    {
        $atributos = $this->getAtributosComprobante();

        $certificado = new Certificado(CFDI_CERT_DIR . DIRECTORY_SEPARATOR . AppSettings::get('cfdi', 'cerfile'));
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

    public function setDocumentosRelacionados(array $foliosfiascales, string $tiporelacion)
    {
        if (empty($foliosfiascales)) {
            return;
        }

        foreach ($foliosfiascales as $folio) {
            $this->comprobante->addCfdiRelacionado([
                'UUID' => $folio,
            ]);
        }

        $this->comprobante->getCfdiRelacionados()->addAttributes([
            'TipoRelacion' => $tiporelacion
        ]);
    }

    protected function buildConceptoTraslado($linea)
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
}
