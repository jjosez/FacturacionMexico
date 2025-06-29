<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Service;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\CFDI\CfdiQuickReader;
use FacturaScripts\Dinamic\Lib\CFDI\PDF\PDFCfdi;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class CfdiEmailService
{
    public static function send(CfdiCliente $cfdi, FacturaCliente $factura, string $xml): bool
    {
        $cliente = $factura->getSubject();
        if (empty($cliente->email)) return false;

        $reader = new CfdiQuickReader($xml);
        $logoID = $factura->getCompany()->idlogo;
        $pdf = (new PDFCfdi($reader, $logoID))->getPdfBuffer();

        $tmpPath = CFDI_DIR . '/tmp/' . $cfdi->uuid;
        Tools::folderCheckOrCreate(CFDI_DIR . '/tmp');

        file_put_contents("$tmpPath.pdf", $pdf);
        file_put_contents("$tmpPath.xml", $xml);

        $mail = new NewMail();
        $mail->to($cliente->email, $cliente->nombre);
        $mail->title = 'Factura ' . $cfdi->uuid;
        $mail->text = 'Gracias por su compra.';
        $mail->addAttachment("$tmpPath.pdf", $cfdi->uuid . '.pdf');
        $mail->addAttachment("$tmpPath.xml", $cfdi->uuid . '.xml');

        $sent = $mail->send();

        unlink("$tmpPath.pdf");
        unlink("$tmpPath.xml");

        return $sent;
    }
}
