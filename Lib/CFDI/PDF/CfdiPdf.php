<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\PDF;

use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos\RegimenFiscal;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos\UsoCfdi;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiQuickReader;

class CfdiPdf
{
    private $builder;
    private $reader;

    public function __construct(CfdiQuickReader $reader)
    {
        $this->builder = new CfdiPdfWrapper();
        $this->reader = $reader;
    }

    private function agregarBloqueEncabezado()
    {
        $comprobante = $this->reader->getComprobante();
        $currentY = $this->builder->getCurrentY();

        $folio = '<b>' . $comprobante['Folio'] . '</b>';
        $this->builder->addText($folio, [], 12);
        $this->builder->addLineBreak(5);

        $text = 'Lugar de expedicion: ' . $comprobante['LugarExpedicion'];
        $this->builder->addText($text, [], 8);
        $this->builder->addLineBreak(4);

        $this->builder->setCurrentY($currentY);
        $option = [ 'justification' => 'right'];
        $folio = '<b>UUID: ' . $this->reader->getTimbreFiscal()['UUID'] . '</b>';
        $this->builder->addText($folio, $option, 12);
        $this->builder->addLineBreak(5);

        $text = 'Fecha de expedicion: ' . $comprobante['Fecha'];
        $this->builder->addText($text, $option, 8);
        $this->builder->addLineBreak(4);

        $this->builder->drawLine();
        $this->builder->addLineBreak(8);
    }

    private function agregarBloqueEmisor()
    {
        $emisor = $this->reader->getEmisor();

        $this->builder->addText('<b>Emisor:</b>');
        $this->builder->addLineBreak(4);

        $this->builder->addText($emisor['Nombre'], [], 8);
        $this->builder->addText($emisor['Rfc'], [], 8);

        $regimen = (new RegimenFiscal())->get($emisor['RegimenFiscal']);
        $text = 'Regimen: ' . $regimen->id . ' - ' . $regimen->descripcion;
        $this->builder->addText($text, [], 8);
        $this->builder->addLineBreak(5);
    }

    private function agregarBloqueReceptor()
    {
        $receptor = $this->reader->getReceptor();

        $this->builder->addText('<b>Receptor:</b>');
        $this->builder->addLineBreak(4);

        $this->builder->addText($receptor['RFC']);

        $uso = (new UsoCfdi())->get($receptor['UsoCFDI']);
        $text = 'Uso CFDI: ' . $uso->id . ' - ' . $uso->descripcion;
        $this->builder->addText($text, [], 8);
    }

    private function agregarBloqueTimbre()
    {
        $timbre = $this->reader->getTimbreFiscal();

        $options = [
            'justification' => 'left',
            'aleft' => 200
        ];

        $this->builder->addText('<b>Sello digital del SAT:</b>', $options);
        $this->builder->addText($timbre['SelloSat'], $options, 8);
        $this->builder->addLineBreak(5);

        $this->builder->addText('<b>Sello Digital del CFDI:</b>', $options);
        $this->builder->addText($timbre['SelloCFD'], $options, 8);
    }

    public function testPdf()
    {
        $this->agregarBloqueEncabezado();
        $this->agregarBloqueEmisor();
        $this->agregarBloqueReceptor();
        $this->agregarBloqueTimbre();

        return $this->builder->getResult();
    }

}