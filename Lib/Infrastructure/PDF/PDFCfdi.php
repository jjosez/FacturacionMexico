<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\PDF;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\SAT\CfdiCatalogo;
use Luecano\NumeroALetras\NumeroALetras;

class PDFCfdi extends PDFCfdiCore
{
    private CfdiData $cfdi;
    private CfdiParser $parser;

    private ?string $logoID;

    public function __construct(CfdiData $cfdi, CfdiParser $parser, ?string $logoID = '')
    {
        parent::__construct();

        $this->cfdi = $cfdi;
        $this->parser = $parser;
        $this->logoID = $logoID;
    }

    public function downloadPDF()
    {
        $this->buildPdf();
        $this->pdf->ezStream(['compress' => 0]);
    }

    public function getPdfBuffer()
    {
        $this->buildPdf();
        return $this->pdf->ezOutput();
    }

    private function buildPdf()
    {
        $this->insertPageNum();
        $this->insertPageHeader();
        $this->insertReceptor();
        $this->insertEmisor();
        $this->insertLogo();
        $this->insertTablaConceptos();
        $this->insertTablaTotales();
        $this->insertTimbreFiscal();
        $this->insertPageFooter();
    }

    private function insertPageHeader()
    {
        $cursorPosition = $this->getCursorPosition();
        $option = ['justification' => 'right'];

        $this->writeTextBold($this->cfdi->folio ?? '', [], 12, 5);

        $text = 'Lugar de expedicion: ' . $this->cfdi->lugarExpedicion;
        $this->writeText($text, [], 8, 4);

        $this->setCursorPosition($cursorPosition);
        $text = 'UUID: ' . $this->cfdi->uuid;
        $this->writeTextBold($text, $option, 12, 5);

        $text = 'Fecha de expedicion: ' . $this->cfdi->fecha;
        $this->writeText($text, $option, 8, 4);

        $this->drawLine();
        $this->moveCursorPosition(8);
    }

    private function insertEmisor()
    {
        $this->writeTextBold('Emisor:');
        $this->moveCursorPosition(4);

        $this->writeText($this->cfdi->emisorNombre ?? '', [], 8);
        $this->writeText($this->cfdi->emisorRfc, [], 8);

        $this->writeTextWrapped(60, $this->catalogo()->regimenFiscal()->getDescripcion($this->cfdi->emisorRegimenFiscal ?? ''), [], 8);
        $this->moveCursorPosition(5);

        $text = 'Numero certificado: ' . $this->cfdi->noCertificado;
        $this->writeText($text, [], 8);

        $text = 'Tipo de comprobante: ' . $this->cfdi->tipoComprobante;
        $this->writeText($text, [], 8);
    }

    private function insertReceptor()
    {
        $cursorPosition = $this->getCursorPosition();
        $options = ['justification' => 'right'];

        $this->writeTextBold('Receptor:', $options);
        $this->moveCursorPosition(4);

        $this->writeText($this->cfdi->receptorNombre ?? '', $options, 8);
        if ('XEXX010101000' === $this->cfdi->receptorRfc) {
            $this->writeText($this->cfdi->receptorNumRegIdTrib ?? '', $options, 8);
        }
        $this->writeText($this->cfdi->receptorRfc, $options, 8);
        $this->moveCursorPosition(4);

        $text = 'Uso cfdi: ' . $this->catalogo()->usoCfdi()->getDescripcion($this->cfdi->receptorUsoCfdi ?? '');
        $this->writeText($text, ['justification' => 'right'], 8);
        $this->moveCursorPosition(4);

        $text = 'Observacion: ' . $this->cfdi->addendaObservaciones;
        $this->writeText($text, ['justification' => 'right'], 8);
        $this->setCursorPosition($cursorPosition);
    }

    private function insertPagos()
    {
        $this->moveCursorPosition(10);
        $text = 'Metodo de pago: ' . $this->cfdi->metodoPago
            . ' Forma de pago: ' . $this->cfdi->formaPago;
        $this->writeText($text, [], 8);
    }

    private function insertLogo()
    {
        if (empty($this->logoID)) return;

        $logoFile = new AttachedFile();
        if ($logoFile->loadFromCode($this->logoID) && file_exists($logoFile->path)) {
            $this->addImageFromAttachedFile($logoFile, 260, 650, 80);
        }
    }

    private function insertTablaConceptos()
    {
        $this->setCursorPosition(640);
        $data = array_map(static fn(array $concepto): array => [
            'cantidad' => $concepto['Cantidad'],
            'id' => $concepto['NoIdentificacion'],
            'descripcion' => $concepto['Descripcion'],
            'clavesat' => $concepto['ClaveProdServ'],
            'claveum' => $concepto['ClaveUnidad'],
            'precio' => $concepto['ValorUnitario'],
            'importe' => $concepto['Importe'],
        ], $this->cfdi->conceptos);

        $cols = [
            'cantidad' => 'Cantidad',
            'id' => 'N. Identificacion',
            'descripcion' => 'Descripcion',
            'clavesat' => 'Clave SAT',
            'claveum' => 'Clave UM',
            'precio' => 'Precio',
            'importe' => 'Total'
        ];

        $options = [
            'fontSize' => 8,
            'gridlines' => 0,
            'rowGap' => 4,
            'showHeadings' => 1,
            'shaded' => 1,
            'shadeCol' => [238 / 255, 239 / 255, 240 / 255],//'shadeCol' => array(0.75, 0.8, 0.8)
            'shadeHeadingCol' => [226 / 255, 232 / 255, 237 / 255],//'shadeHeadingCol'=> [0.75,0.8,0.78]
            'width' => $this->getUsablePageWidth()
        ];

        $this->moveCursorPosition(2);
        $this->drawTable($data, $cols, $options);
    }

    private function insertTablaTotales()
    {
        $data = array(
            [
                'subtotal' => $this->cfdi->subtotal,
                'descuento' => $this->cfdi->descuento,
                'iva' => $this->cfdi->impuestos['totalTrasladados'],
                'retenciones' => 0.00
            ]
        );

        $cols = [
            'subtotal' => 'SUBTOTAL',
            'descuento' => 'DESCUENTO',
            'iva' => 'IVA',
            'retenciones' => 'RETENCIONES'
        ];

        $options = [
            'fontSize' => 8,
            'gridlines' => 0,
            'rowGap' => 4,
            'showHeadings' => 1,
            'shaded' => 2,
            'shadeCol' => [226 / 255, 232 / 255, 237 / 255],
            'width' => $this->getUsablePageWidth(),
        ];

        $position = $this->getCursorPosition();

        if ($position < 315) {
            $this->pdf->ezNewPage();
        } else {
            $this->setCursorPosition(315);
        }

        $this->drawTable($data, $cols, $options);
        $this->moveCursorPosition(3);

        $this->writeTextBold('Total:');
        $this->writeText(strtoupper((new NumeroALetras())->toInvoice($this->cfdi->total, 2, $this->cfdi->moneda)));
        $this->moveCursorPosition(5);
    }

    private function insertTimbreFiscal()
    {
        $options = [
            'justification' => 'left',
            'aleft' => 175
        ];

        $this->writeTextBold('Cadena Original del complemento de certificacion digital del SAT:');
        $this->writeText($this->cadenaOrigen(), [], 8);
        $this->moveCursorPosition(5);

        $this->writeTextBold('Sello Digital del SAT:', $options);
        $this->writeText($this->cfdi->selloSat ?? '', $options, 8);
        $this->moveCursorPosition(5);

        $this->writeTextBold('Sello Digital del CFDI:', $options);
        $this->writeText($this->cfdi->selloCfd ?? '', $options, 8);
        $this->moveCursorPosition(5);

        $this->insertTablaCertificados();
        $this->insertQrCode();
    }

    private function insertTablaCertificados()
    {
        $data = array(
            [
                'certsat' => $this->cfdi->noCertificadoSat,
                'fechatimbre' => $this->cfdi->fechaTimbrado,
                'rfcprov' => $this->cfdi->rfcProvCertif
            ]
        );

        $cols = [
            'certsat' => '<b>Certificado del SAT:</b>',
            'fechatimbre' => '<b>Fecha del Timbrado:</b>',
            'rfcprov' => '<b>RfcProvCertif:</b>'
        ];

        $options = [
            'fontSize' => 8,
            'gridlines' => 0,
            'showHeadings' => 1,
            'width' => 412,
            'xOrientation' => 'left',
            'xPos' => 'right',
        ];

        $this->drawTable($data, $cols, $options);
    }

    private function insertQrCode()
    {
        if ($this->cfdi->uuid === null) {
            return;
        }

        $qrFile = CFDI_DIR . DIRECTORY_SEPARATOR . 'qrcode.png';
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_Q,
            'quietzoneSize' => 3
        ]);

        $qrcode = new QRCode($options);
        $qrcode->render($this->parser->qrCodeUrl(), $qrFile);

        $this->moveCursorPosition(10);
        $this->insertPngImage($qrFile, 30, 140);
    }

    public function insertPageFooter()
    {
        $this->moveCursorPosition(5);
        $this->writeText('Este documento es la representación impresa de un CFDI', ['justification' => 'center']);
    }

    public function insertPageNum()
    {
        $pattern = 'Pagina {PAGENUM} / {TOTALPAGENUM}';
        $this->pdf->ezStartPageNumbers(self::MARGIN_L, 30, 9, '', $pattern, 1);
    }

    private function catalogo(): CfdiCatalogo
    {
        return new CfdiCatalogo();
    }

    private function cadenaOrigen(): string
    {
        return $this->parser->cadenaOrigen();
    }
}
