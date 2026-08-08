<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use Exception;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import\SupplierCfdiImportService;

/**
 * Adaptador de compatibilidad para el flujo anterior de importación.
 */
class CfdiSupplierInvoiceImporter
{
    /**
     * @throws Exception
     */
    public function import(CfdiProveedor $cfdi, Proveedor $supplier, array $conceptos): FacturaProveedor
    {
        $options = new ImportOptions([
            'productAction' => ImportOptions::PRODUCT_ACTION_SKIP,
            'autoMatchProducts' => true,
        ]);

        $result = (new SupplierCfdiImportService())->importSingle(
            $cfdi,
            $supplier,
            $options,
            $conceptos
        );

        if (!$result->success || $result->invoice === null) {
            throw new Exception($result->error ?? 'No se pudo generar la factura del proveedor');
        }

        return $result->invoice;
    }
}
