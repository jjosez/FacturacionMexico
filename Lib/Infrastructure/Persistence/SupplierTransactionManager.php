<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Base\DataBase;

final class SupplierTransactionManager
{
    private ?DataBase $database = null;

    public function begin(): void
    {
        $this->database = new DataBase();
        $this->database->beginTransaction();
    }

    public function commit(): void
    {
        if ($this->database === null) {
            return;
        }

        $this->database->commit();
        $this->database = null;
    }

    public function rollback(): void
    {
        if ($this->database === null) {
            return;
        }

        $this->database->rollBack();
        $this->database = null;
    }
}
