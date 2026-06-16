<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Contracts;

use PhpSoftBox\DatabaseLookup\LookupSpec;

interface DatabaseBulkValidationAdapterInterface extends DatabaseValidationAdapterInterface
{
    /**
     * Создаёт bulk-запрос найденных значений по lookup-спецификации.
     */
    public function existingValues(LookupSpec $lookup, ?string $connection = null): ExistingValuesQueryInterface;
}
