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

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\EstadoDocumento;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\BusinessDocumentGenerator;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;

/**
 * Service for generating invoices from delivery notes (remisiones)
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class DeliveryNoteInvoiceGenerator
{
    /** @var DataBase */
    protected DataBase $dataBase;

    public function __construct()
    {
        $this->dataBase = new DataBase();
    }

    /**
     * Groups multiple delivery notes into a single invoice
     *
     * @param array $deliveryNoteCodes Array of delivery note IDs
     * @return FacturaCliente|null The generated invoice or null on failure
     * @throws Exception
     */
    public function groupAndInvoice(array $deliveryNoteCodes, array $properties = []): ?FacturaCliente
    {
        if (empty($deliveryNoteCodes)) {
            throw new Exception('no-selected-item');
        }

        // Load all delivery notes
        $deliveryNotes = $this->loadDeliveryNotes($deliveryNoteCodes);

        // Validate same customer
        $this->validateSameCustomer($deliveryNotes);

        // Start transaction
        $this->dataBase->beginTransaction();

        try {
            // Use first delivery note as prototype
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

            // Generate invoice using BusinessDocumentGenerator
            $generator = new BusinessDocumentGenerator();
            $prototypeClone = clone $prototype;

            // Apply custom properties (fecha, codserie, etc.)
            if (false === $generator->generate($prototypeClone, 'FacturaCliente', $allLines, $quantities, $properties)) {
                throw new Exception('cannot-generate-invoice');
            }

            // Get the generated invoice
            $invoices = $generator->getLastDocs();
            if (empty($invoices)) {
                throw new Exception('cannot-generate-invoice');
            }

            /** @var FacturaCliente $invoice */
            $invoice = $invoices[0];

            // Apply additional properties that BusinessDocumentGenerator doesn't handle
            if (isset($properties['cfdiglobal']) && $properties['cfdiglobal']) {
                $invoice->cfdiglobal = true;
                if (!$invoice->save()) {
                    throw new Exception('cannot-save-invoice-properties');
                }
            }

            // Update all delivery notes status to invoiced
            foreach ($deliveryNotes as $note) {
                $this->updateDeliveryNoteStatus($note);
            }

            $this->dataBase->commit();
            Tools::log()->notice('invoice-created-successfully');

            return $invoice;
        } catch (Exception $e) {
            $this->dataBase->rollback();
            Tools::log()->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * Generates individual invoices for each delivery note
     *
     * @param array $deliveryNoteCodes Array of delivery note IDs
     * @param bool $sameDate Whether to use the delivery note date for the invoice
     * @return array Array of generated invoices
     * @throws Exception
     */
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

                // Generate invoice for this delivery note
                $generator = new BusinessDocumentGenerator();

                // Set the same date flag if requested
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

                // Reset same date flag
                if ($sameDate) {
                    BusinessDocumentGenerator::setSameDate(false);
                }

                // Get the generated invoice
                $generatedInvoices = $generator->getLastDocs();
                if (empty($generatedInvoices)) {
                    throw new Exception('cannot-generate-invoice-for-note: ' . $note->codigo);
                }

                $invoice = $generatedInvoices[0];
                $invoices[] = $invoice;

                // Update delivery note status
                $this->updateDeliveryNoteStatus($note);
            }

            $this->dataBase->commit();
            Tools::log()->notice('invoices-created-successfully');

            return $invoices;
        } catch (Exception $e) {
            $this->dataBase->rollback();
            Tools::log()->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * Loads delivery notes from codes
     *
     * @param array $codes
     * @return AlbaranCliente[]
     * @throws Exception
     */
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

    /**
     * Validates that all delivery notes belong to the same customer
     *
     * @param AlbaranCliente[] $deliveryNotes
     * @throws Exception
     */
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

            // Additional validation: check if note is in an invoiceable state
            if (!$note->editable) {
                throw new Exception('delivery-note-not-editable: ' . $note->codigo);
            }
        }
    }

    /**
     * Updates delivery note status to invoiced
     *
     * @param AlbaranCliente $note
     * @throws Exception
     */
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

    /**
     * Gets the invoiced status for delivery notes
     *
     * @return EstadoDocumento|null
     */
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
