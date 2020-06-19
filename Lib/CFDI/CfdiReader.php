<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;


class CfdiReader
{
    private $cfdi;
    private $complemento;

    public function __construct($xml)
    {
        if (empty($xml)) {
            throw new \InvalidArgumentException('XML invalido');
        }

        $this->cfdi = \CfdiUtils\Cfdi::newFromString($xml);
        $this->complemento = $this->cfdi->getNode();
    }

    public function getConceptos()
    {
        $conceptos = $this->complemento->searchNodes('cfdi:Conceptos', 'cfdi:Concepto');

        return $conceptos;
    }

    public function getMetodoPago()
    {
        return $this->complemento['MetodoPago'];
    }

    public function getSource()
    {
        return $this->cfdi->getSource();
    }

    public function getReceptor()
    {
        $receptor = $this->complemento->searchNode('cfdi:Receptor');

        return $receptor;
    }

    public function getRelacionados()
    {
        $this->cfdi->searchNode('cfdi:CfdiRelacionados');
    }

    public function getUUID()
    {
        $tfd = $this->complemento->searchNode('cfdi:Complemento', 'tfd:TimbreFiscalDigital');

        return $tfd['UUID'];
    }

    public function getVersion()
    {
        return $this->cfdi->getVersion();
    }
}