<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\PDF;

use Cezpdf;
use FacturaScripts\Dinamic\Model\AttachedFile;

class PDFCfdiCore
{
    const DEFAULT_PAGE_SIZE = 'LETTER';
    const FONT_SIZE = 9;
    const FONT_SIZE_L = 12;
    const FONT_SIZE_M = 10;
    const FONT_MAIN = 'Helvetica';
    const MARGIN_L = 30;
    const MARGIN_R = 30;
    const MARGIN_T = 30;
    const MARGIN_B = 30;

    protected $pageWidth;
    protected $pdf;

    public function __construct()
    {
        $this->pdf = new Cezpdf(self::DEFAULT_PAGE_SIZE);

        $this->pdf->ezSetMargins(self::MARGIN_T, self::MARGIN_B, self::MARGIN_L, self::MARGIN_R);
        $this->pdf->selectFont(self::FONT_MAIN);

        $this->pageWidth = $this->pdf->ez['pageWidth'];
    }

    protected function writeText(string $text, array $options = [], $fontSize = '', $lineBreak = 0)
    {
        $fontSize = $fontSize ?: self::FONT_SIZE;

        $this->pdf->ezText($text, $fontSize, $options);
        $this->moveCursorPosition($lineBreak);
    }

    protected function writeTextBold(string $text, array $options = [], $fontSize = '', $lineBreak = 0)
    {
        $fontSize = $fontSize ?: self::FONT_SIZE;

        $text = $this->textBold($text);
        $this->pdf->ezText($text, $fontSize, $options);
        $this->moveCursorPosition($lineBreak);
    }

    protected function writeTextWrapped(float $width, string $text, array $options = [], $fontSize = '', $lineBreak = 0)
    {
        $fontSize = $fontSize ?: self::FONT_SIZE;
        $newText = wordwrap($text, $width);

        $this->pdf->ezText($newText, $fontSize, $options);
        $this->moveCursorPosition($lineBreak);
    }

    protected function moveCursorPosition(int $n)
    {
        $this->pdf->ezSetDy(-$n);
    }

    protected function textBold($text)
    {
        $formato = '<b>%s</b>';
        return sprintf($formato, $text);
    }

    protected function drawLine()
    {
        $xEndPos = $this->pageWidth - self::MARGIN_L;
        $yPos = $this->pdf->y;

        $this->pdf->setLineStyle(0.5);
        $this->pdf->line(self::MARGIN_L, $yPos, $xEndPos, $yPos);
    }

    protected function insertPngImage($file, $x, $w)
    {
        $this->pdf->addPngFromFile($file, $x, $this->getCursorPosition(), $w);
    }

    protected function drawTable(array &$data, array $cols, array $options)
    {
        $this->pdf->ezTable($data, $cols, '', $options);
    }

    protected function getCursorPosition()
    {
        return $this->pdf->y;
    }

    /**
     *
     * @param AttachedFile $file
     * @param int|float $xPos
     * @param int|float $yPos
     * @param int|float $width
     * @param int|float $height
     */
    protected function addImageFromAttachedFile($file, $xPos, $yPos, $width)
    {
        switch ($file->mimetype) {
            case 'image/gif':
                $this->pdf->addGifFromFile($file->path, $xPos, $yPos, $width);
                break;

            case 'image/jpeg':
            case 'image/jpg':
                $this->pdf->addJpegFromFile($file->path, $xPos, $yPos, $width);
                break;

            case 'image/png':
                $this->pdf->addPngFromFile($file->path, $xPos, $yPos, $width);
                break;
        }
    }

    protected function getResult()
    {
        return $this->pdf->ezStream(['compress' => 0]);
    }

    protected function getUsablePageWidth()
    {
        return $this->pageWidth - (self::MARGIN_L + self::MARGIN_R);
    }

    protected function setCursorPosition($y)
    {
        $this->pdf->ezSetY($y);
    }
}
