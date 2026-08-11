<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Build;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\EstadoDocumento;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\BusinessDocumentGenerator;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class DeliveryNoteInvoiceGenerator
{
    protected DataBase $dataBase;

    public function __construct()
    {
        $this->dataBase = new DataBase();
    }

    public function groupAndInvoice(array $deliveryNoteCodes, array $properties = []): ?FacturaCliente
    {
        if (empty($deliveryNoteCodes)) {
            throw new Exception('no-selected-item');
        }

        $deliveryNotes = $this->loadDeliveryNotes($deliveryNoteCodes);
        $this->validateSameCustomer($deliveryNotes);
        $this->dataBase->beginTransaction();

        try {
            $prototype = $deliveryNotes[0];
            $allLines = [];
            $quantities = [];

            foreach ($deliveryNotes as $note) {
                foreach ($note->getLines() as $line) {
                    $allLines[] = $line;
                    $quantities[$line->id()] = $line->cantidad;
                }
            }

            if (empty($allLines)) {
                throw new Exception('no-lines-to-invoice');
            }

            $generator = new BusinessDocumentGenerator();
            $prototypeClone = clone $prototype;

            if (false === $generator->generate($prototypeClone, 'FacturaCliente', $allLines, $quantities, $properties)) {
                throw new Exception('cannot-generate-invoice');
            }

            $invoices = $generator->getLastDocs();
            if (empty($invoices)) {
                throw new Exception('cannot-generate-invoice');
            }

            /** @var FacturaCliente $invoice */
            $invoice = $invoices[0];

            if (isset($properties['cfdiglobal']) && $properties['cfdiglobal']) {
                $invoice->cfdiglobal = true;
                if (!$invoice->save()) {
                    throw new Exception('cannot-save-invoice-properties');
                }
            }

            foreach ($deliveryNotes as $note) {
                $this->updateDeliveryNoteStatus($note);
            }

            $this->dataBase->commit();
            Tools::log()->notice('invoice-created-successfully');

            return $invoice;
        } catch (Exception $e) {
            $this->dataBase->rollback();
            Tools::log('CFDI')->error($e->getMessage());
            throw $e;
        }
    }

    public function generateInvoices(array $deliveryNoteCodes, bool $sameDate = false): array
    {
        if (empty($deliveryNoteCodes)) {
            throw new Exception('no-selected-item');
        }

        $invoices = [];
        $this->dataBase->beginTransaction();

        try {
            foreach ($deliveryNoteCodes as $code) {
                $note = new AlbaranCliente();
                if (!$note->load($code)) {
                    throw new Exception('record-not-found');
                }

                $generator = new BusinessDocumentGenerator();

                if ($sameDate) {
                    BusinessDocumentGenerator::setSameDate(true);
                }

                $prototypeClone = clone $note;
                $lines = $note->getLines();
                $quantities = [];

                foreach ($lines as $line) {
                    $quantities[$line->id()] = $line->cantidad;
                }

                if (false === $generator->generate($prototypeClone, 'FacturaCliente', $lines, $quantities, [])) {
                    throw new Exception('cannot-generate-invoice-for-note: ' . $note->codigo);
                }

                if ($sameDate) {
                    BusinessDocumentGenerator::setSameDate(false);
                }

                $generatedInvoices = $generator->getLastDocs();
                if (empty($generatedInvoices)) {
                    throw new Exception('cannot-generate-invoice-for-note: ' . $note->codigo);
                }

                $invoice = $generatedInvoices[0];
                $invoices[] = $invoice;

                $this->updateDeliveryNoteStatus($note);
            }

            $this->dataBase->commit();
            Tools::log()->notice('invoices-created-successfully');

            return $invoices;
        } catch (Exception $e) {
            $this->dataBase->rollback();
            Tools::log('CFDI')->error($e->getMessage());
            throw $e;
        }
    }

    protected function loadDeliveryNotes(array $codes): array
    {
        $deliveryNotes = [];

        foreach ($codes as $code) {
            $note = new AlbaranCliente();
            if (!$note->load($code)) {
                throw new Exception('record-not-found: ' . $code);
            }
            $deliveryNotes[] = $note;
        }

        return $deliveryNotes;
    }

    protected function validateSameCustomer(array $deliveryNotes): void
    {
        if (empty($deliveryNotes)) {
            throw new Exception('no-delivery-notes-to-validate');
        }

        $firstCustomer = $deliveryNotes[0]->codcliente;

        foreach ($deliveryNotes as $note) {
            if ($note->codcliente !== $firstCustomer) {
                throw new Exception('all-delivery-notes-must-be-from-same-customer');
            }

            if (!$note->editable) {
                throw new Exception('delivery-note-not-editable: ' . $note->codigo);
            }
        }
    }

    protected function updateDeliveryNoteStatus(AlbaranCliente $note): void
    {
        $invoicedStatus = $this->getInvoicedStatus();

        if (!$invoicedStatus) {
            throw new Exception('invoiced-status-not-found-for-delivery-notes');
        }

        $note->setDocumentGeneration(false);
        $note->idestado = $invoicedStatus->idestado;

        if (!$note->save()) {
            throw new Exception('cannot-update-delivery-note-status: ' . $note->codigo);
        }
    }

    protected function getInvoicedStatus(): ?EstadoDocumento
    {
        $where = [
            Where::eq('tipodoc', 'AlbaranCliente'),
            Where::eq('generadoc', 'FacturaCliente')
        ];

        $estados = EstadoDocumento::all($where);
        return !empty($estados) ? $estados[0] : null;
    }
}
