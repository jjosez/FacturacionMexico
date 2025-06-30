<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Services;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\CFDI\CfdiQuickReader;
use FacturaScripts\Dinamic\Lib\CFDI\PDF\PDFCfdi;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class CfdiEmailService
{
    public static function send(
        CfdiCliente $cfdi,
        FacturaCliente $factura,
        string $xml,
        string $subject = '',
        string $body = ''
    ): bool
    {
        if (!self::canSendEmail($factura)) {
            return false;
        }

        $paths = self::generateAttachments($cfdi, $factura, $xml);
        if (!$paths) {
            Tools::log()->error("Error al adjuntar archivos del cfdi: $cfdi->uuid");
            return false;
        }

        $sent = self::sendEmail($cfdi, $factura, $subject, $body, $paths);

        self::cleanupAttachments($paths);

        return $sent;
    }

    private static function canSendEmail(FacturaCliente $factura): bool
    {
        $cliente = $factura->getSubject();
        if (empty($cliente->email)) {
            Tools::log()->warning('No se pudo enviar el email: el cliente no tiene email asignado.');
            return false;
        }
        return true;
    }

    private static function generateAttachments(CfdiCliente $cfdi, FacturaCliente $factura, string $xml): ?array
    {
        Tools::folderCheckOrCreate(CFDI_DIR . '/tmp');

        $reader = new CfdiQuickReader($xml);
        $logoID = $factura->getCompany()->idlogo;
        $pdf = (new PDFCfdi($reader, $logoID))->getPdfBuffer();

        $basePath = CFDI_DIR . '/tmp/' . $cfdi->uuid;
        $pdfPath = $basePath . '.pdf';
        $xmlPath = $basePath . '.xml';

        if (false === file_put_contents($pdfPath, $pdf)) {
            return null;
        }

        if (false === file_put_contents($xmlPath, $xml)) {
            unlink($pdfPath);
            return null;
        }

        return ['pdf' => $pdfPath, 'xml' => $xmlPath];
    }

    private static function sendEmail(
        CfdiCliente $cfdi,
        FacturaCliente $factura,
        string $subject,
        string $body,
        array $paths
    ): bool
    {
        $cliente = $factura->getSubject();
        $sent = false;

        try {
            $mail = new NewMail();
            $mail->to($cliente->email, $cliente->nombre);
            $mail->title = $subject ?: 'Factura ' . $cfdi->uuid;
            $mail->text = $body ?: 'Gracias por su compra.';
            $mail->addAttachment($paths['pdf'], $cfdi->uuid . '.pdf');
            $mail->addAttachment($paths['xml'], $cfdi->uuid . '.xml');

            $sent = $mail->send();
        } catch (Exception $e) {
            Tools::log()->error("Error al enviar el correo del CFDI: {$e->getMessage()}");
        }

        if ($sent) {
            Tools::log()->notice("CFDI enviado por email correctamente: $cfdi->uuid");
        } else {
            Tools::log()->error("Error enviando el CFDI: $cfdi->uuid");
        }

        return $sent;
    }

    private static function cleanupAttachments(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
