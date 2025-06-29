<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogs;

class CatalogoUsoCFDI extends SatCatalogBase
{
    protected function fileName(): string
    {
        return 'c_UsoCFDI.json';
    }

    /**
     * Returns CFDI usage options applicable for a given entity type ('fisica' or 'moral').
     *
     * @param string $entityType
     * @return array
     */
    public function getUsageByEntity(string $entityType): array
    {
        $field = $this->resolveEntityTypeField($entityType);

        return array_filter($this->all(), function ($item) use ($field) {
            return $this->appliesToEntityType($item, $field);
        });
    }

    /**
     * Maps the entity type to its corresponding field name in the catalog.
     *
     * @param string $entityType
     * @return string
     */
    protected function resolveEntityTypeField(string $entityType): string
    {
        return match (strtolower(trim($entityType))) {
            'fisica' => 'aplicaParaTipoPersonaFisica',
            'moral' => 'aplicaParaTipoPersonaMoral',
            default => throw new \InvalidArgumentException("Tipo de persona no válido: $entityType"),
        };
    }

    /**
     * Checks if a CFDI usage item applies to the specified entity type field.
     *
     * @param object $item
     * @param string $field
     * @return bool
     */
    protected function appliesToEntityType(object $item, string $field): bool
    {
        return isset($item->{$field}) && strtolower($item->{$field}) === 'sí';
    }
}
