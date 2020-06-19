<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;


class CfdiQuickReader
{
    private $cfdi;
    private $comprobante;

    public function __construct($xml)
    {
        if (empty($xml)) {
            throw new \InvalidArgumentException('XML invalido');
        }

        $this->cfdi = \CfdiUtils\Cfdi::newFromString($xml);
        $this->comprobante = $this->cfdi->getQuickReader();
    }

    public function getConceptos()
    {
        $conceptos = $this->comprobante->conceptos;

        return $conceptos();
    }

    public function getComprobante()
    {
        return $this->comprobante;
    }

    public function getEmisor()
    {
        return $this->comprobante->emisor;
    }

    public function getMetodoPago()
    {
        return $this->comprobante->metodopago;
    }

    public function getSource()
    {
        return $this->cfdi->getSource();
    }

    public function getReceptor()
    {
        return $this->comprobante->receptor;
    }

    public function getRelacionados()
    {
        [];
    }

    public function getTimbreFiscal()
    {
        return $this->comprobante->complemento->timbreFiscalDigital;
    }

    public function getVersion()
    {
        return $this->cfdi->getVersion();
    }
}