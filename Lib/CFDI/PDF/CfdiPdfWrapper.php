<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\PDF;

use Cezpdf;

class CfdiPdfWrapper
{
    const DEFAULT_PAGE_SIZE = 'LETTER';
    const FONT_SIZE = 9;
    const FONT_MAIN = 'Helvetica';

    private $pdf;
    private $CurrentPosY;

    public function __construct()
    {
        $this->pdf = new Cezpdf(self::DEFAULT_PAGE_SIZE);
        $this->pdf->ezSetMargins(20, 20, 20, 20);
        $this->pdf->selectFont(self::FONT_MAIN);
    }

    public function getPageSettings()
    {
        return $this->pdf->ez;
    }

    public function addText(string $text, array $options = [], $fontSize = '')
    {
        $fontSize = $fontSize ?: self::FONT_SIZE;

        $this->pdf->ezText($text, $fontSize, $options);
    }

    public function addLineBreak(int $n)
    {
        $this->pdf->ezSetY($this->pdf->y - $n);
    }

    public function drawImage($file)
    {
        $y = $this->getCurrentY();
        $this->pdf->addPngFromFile($file, 230, $y, 175);
    }

    public function drawLine()
    {
        $x1 = $this->pdf->ez['leftMargin'];
        $x2 = $this->pdf->ez['pageWidth'] - $x1;
        $y = $this->pdf->y;

        $this->pdf->setLineStyle(0.5);
        $this->pdf->line($x1, $y, $x2, $y);
    }

    public function getCurrentY()
    {
        return $this->pdf->y;
    }

    public function getResult()
    {
        return $this->pdf->ezStream(['compress'=>0]);
    }

    public function restoreState()
    {
        $this->pdf->restoreState();
    }

    public function setCurrentY($y)
    {
        $this->pdf->ezSetY($y);
    }

    public function saveState()
    {
        $this->pdf->saveState();
    }
}