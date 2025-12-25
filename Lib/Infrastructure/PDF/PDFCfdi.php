<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\PDF;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiQuickReader;

class PDFCfdi extends PDFCfdiCore
{
    private CfdiQuickReader $reader;

    private ?string $logoID;

    public function __construct(CfdiQuickReader $reader, ?string $logoID = '')
    {
        parent::__construct();

        $this->reader = $reader;
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

        $this->writeTextBold($this->reader->folio(), [], 12, 5);

        $text = 'Lugar de expedicion: ' . $this->reader->lugarExpedicion();
        $this->writeText($text, [], 8, 4);

        $this->setCursorPosition($cursorPosition);
        $text = 'UUID: ' . $this->reader->uuid();
        $this->writeTextBold($text, $option, 12, 5);

        $text = 'Fecha de expedicion: ' . $this->reader->fechaExpedicion();
        $this->writeText($text, $option, 8, 4);

        $this->drawLine();
        $this->moveCursorPosition(8);
    }

    private function insertEmisor()
    {
        $this->writeTextBold('Emisor:');
        $this->moveCursorPosition(4);

        $this->writeText($this->reader->emisorNombre(), [], 8);
        $this->writeText($this->reader->emisorRfc(), [], 8);

        $this->writeTextWrapped(60, $this->reader->emisorRegimenFiscal(), [], 8);
        $this->moveCursorPosition(5);

        $text = 'Numero certificado: ' . $this->reader->noCertificado();
        $this->writeText($text, [], 8);

        $text = 'Tipo de comprobante: ' . $this->reader->tipoComprobamte();
        $this->writeText($text, [], 8);
    }

    private function insertReceptor()
    {
        $cursorPosition = $this->getCursorPosition();
        $options = ['justification' => 'right'];

        $this->writeTextBold('Receptor:', $options);
        $this->moveCursorPosition(4);

        $this->writeText($this->reader->receptorNombre(), $options, 8);
        if ('XEXX010101000' === $this->reader->receptorRfc()) {
            $this->writeText($this->reader->receptorIdTrib(), $options, 8);
        }
        $this->writeText($this->reader->receptorRfc(), $options, 8);
        $this->moveCursorPosition(4);

        $text = 'Uso cfdi: ' . $this->reader->receptorUsoCfdi();
        $this->writeText($text, ['justification' => 'right'], 8);
        $this->moveCursorPosition(4);

        $text = 'Observacion: ' . $this->reader->addendaObservaciones();
        $this->writeText($text, ['justification' => 'right'], 8);
        $this->setCursorPosition($cursorPosition);
    }

    private function insertPagos()
    {
        $this->moveCursorPosition(10);
        $text = 'Metodo de pago: ' . $this->reader->metodoPago()
            . ' Forma de pago: ' . $this->reader->formaPago();
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
        $data = $this->reader->conceptosData();

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
                'subtotal' => $this->reader->subTotal(),
                'descuento' => $this->reader->totalDescuentos(),
                'iva' => $this->reader->totalImpuestosTrasladados(),
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
        $this->writeText(strtoupper($this->reader->totalLetra()));
        $this->moveCursorPosition(5);
    }

    private function insertTimbreFiscal()
    {
        $options = [
            'justification' => 'left',
            'aleft' => 175
        ];

        $this->writeTextBold('Cadena Original del complemento de certificacion digital del SAT:');
        $this->writeText($this->reader->cadenaOrigen(), [], 8);
        $this->moveCursorPosition(5);

        $this->writeTextBold('Sello Digital del SAT:', $options);
        $this->writeText($this->reader->selloSat(), $options, 8);
        $this->moveCursorPosition(5);

        $this->writeTextBold('Sello Digital del CFDI:', $options);
        $this->writeText($this->reader->selloCfd(), $options, 8);
        $this->moveCursorPosition(5);

        $this->insertTablaCertificados();
        $this->insertQrCode();
    }

    private function insertTablaCertificados()
    {
        $data = array(
            [
                'certsat' => $this->reader->noCertificadoSAT(),
                'fechatimbre' => $this->reader->fechaTimbrado(),
                'rfcprov' => $this->reader->proveedorCertif()
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
        $qrFile = CFDI_DIR . DIRECTORY_SEPARATOR . 'qrcode.png';
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_Q,
            'quietzoneSize' => 3
        ]);

        $qrcode = new QRCode($options);
        $qrcode->render($this->reader->qrCodeUrl(), $qrFile);

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
}
