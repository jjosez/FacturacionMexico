<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Base\DataBase;

final class SupplierQueueStorage
{
    private DataBase $database;

    public function __construct(?DataBase $database = null)
    {
        $this->database = $database ?? new DataBase();
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->database->select($sql, $params);
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->database->exec($sql, $params);
    }

    public function lastIdentity(): int
    {
        return (int)$this->database->getLastIdentity();
    }

    public function affectedRows(): int
    {
        return (int)$this->database->getAffectedRows();
    }
}
