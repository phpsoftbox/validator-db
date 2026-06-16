<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Contracts;

interface DatabaseBulkValidationAdapterInterface extends DatabaseValidationAdapterInterface
{
    /**
     * Создаёт bulk-запрос найденных значений колонки по списку значений и фиксированным критериям.
     *
     * @param list<mixed> $values
     * @param array<string, mixed> $criteria
     */
    public function existingValues(
        string $table,
        string $column,
        array $values,
        array $criteria = [],
        ?string $connection = null,
    ): ExistingValuesQueryInterface;
}
