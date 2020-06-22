<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\PDF;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiQuickReader;

class PDFCfdi extends PDFCfdiCore
{
    private $data;

    public function __construct(CfdiQuickReader $data)
    {
        parent::__construct();

        $this->data = $data;
    }

    public function testPdf()
    {
        $this->insertPageNum();
        $this->insertPageHeader();
        $this->insertEmisor();
        $this->insertReceptor();
        $this->insertLogo();
        $this->insertTablaConceptos();
        $this->insertTablaTotales();
        $this->insertTimbreFiscal();
        $this->insertPageFooter();

        return $this->getResult();
    }

    private function insertPageHeader()
    {
        $cursorPosition = $this->getCursorPosition();
        $option = ['justification' => 'right'];

        $this->writeTextBold($this->data->folio(), [], 12, 5);

        $text = 'Lugar de expedicion: ' . $this->data->lugarExpedicion();
        $this->writeText($text, [], 8, 4);

        $this->setCursorPosition($cursorPosition);
        $text = 'UUID: ' . $this->data->uuid() . $this->getCursorPosition();
        $this->writeTextBold($text, $option, 12, 5);

        $text = 'Fecha de expedicion: ' . $this->data->fechaExpedicion();
        $this->writeText($text, $option, 8, 4);

        $this->drawLine();
        $this->moveCursorPosition(8);
    }

    private function insertEmisor()
    {
        $cursorPosition = $this->getCursorPosition();

        $this->writeTextBold('Emisor:');
        $this->moveCursorPosition(4);

        $this->writeText($this->data->emisorNombre(), [], 8);
        $this->writeText($this->data->emisorRfc(), [], 8);

        $this->writeText($this->data->emisorRegimenFiscal(), [], 8);
        $this->moveCursorPosition(5);

        $this->setCursorPosition($cursorPosition);
        $this->writeText('Numero Certificado: 123456789', ['justification' => 'right'], 8);
        $this->writeText('Tipo de comprobante: I', ['justification' => 'right'], 8);
    }

    private function insertReceptor()
    {
        $this->setCursorPosition(675);
        $this->writeTextBold('Receptor:');
        $this->moveCursorPosition(4);

        $this->writeText($this->data->receptorRfc());

        $text = 'Uso CFDI: ' . $this->data->receptorUsoCfdi();
        $this->writeText($text, [], 8);

        $this->moveCursorPosition(10);
        $text = 'Metodo de pago: ' . $this->data->metodoPago()
            . ' Forma de pago: ' . $this->data->formaPago();
        $this->writeText($text, [], 8);
    }

    private function insertLogo()
    {
        $logoFile = new AttachedFile();
        if ($logoFile->loadFromCode(1) && file_exists($logoFile->path)) {
            $this->addImageFromAttachedFile($logoFile, 230, 630, 175);
        }
    }

    private function insertTablaConceptos()
    {
        $data = $this->data->conceptosData();

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
                'subtotal' => $this->data->subTotal(),
                'descuento' => $this->data->totalDescuentos(),
                'iva' => $this->data->totalImpuestosTrasladados(),
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
        $this->writeText(strtoupper($this->data->totalLetra()));
        $this->moveCursorPosition(5);
    }

    private function insertTimbreFiscal()
    {
        $options = [
            'justification' => 'left',
            'aleft' => 175
        ];

        $this->writeTextBold('Cadena Original del complemento de certificacion digital del SAT:');
        $this->writeText($this->data->cadenaOrigen(), [], 8);
        $this->moveCursorPosition(5);

        $this->writeTextBold('Sello Digital del SAT:', $options);
        $this->writeText($this->data->selloSat(), $options, 8);
        $this->moveCursorPosition(5);

        $this->writeTextBold('Sello Digital del CFDI:', $options);
        $this->writeText($this->data->selloCfd(), $options, 8);
        $this->moveCursorPosition(5);

        $this->insertTablaCertificados();
        $this->insertQrCode();
    }

    private function insertTablaCertificados()
    {
        $data = array(
            [
                'certsat' => $this->data->certificadoSAT(),
                'fechatimbre' => $this->data->fechaTimbrado(),
                'rfcprov' => $this->data->proveedorCertif()
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
        $qrcode->render($this->data->qrCodeUrl(), $qrFile);

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