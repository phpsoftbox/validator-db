<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Tests\Support;

use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Validator\Db\Contracts\DatabaseBulkValidationAdapterInterface;
use PhpSoftBox\Validator\Db\Contracts\ExistingValuesQueryInterface;

final class TestDatabaseAdapter implements DatabaseBulkValidationAdapterInterface
{
    public ?string $lastTable = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastCriteria = null;

    public ?string $lastConnection = null;

    public ?string $lastIgnoreColumn = null;

    public mixed $lastIgnoreValue = null;

    public ?string $lastExistingTable = null;

    public ?string $lastExistingColumn = null;

    /**
     * @var list<mixed>|null
     */
    public ?array $lastExistingValues = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastExistingCriteria = null;

    public ?string $lastExistingConnection = null;

    /**
     * @var list<string>
     */
    public array $lastExistingWarmupKeyColumns = [];

    public int $existingValuesCalls = 0;

    public int $existingValuesWarmupFetches = 0;

    /**
     * @param list<mixed> $existingValues
     */
    public function __construct(
        private bool $exists,
        private bool $unique,
        private array $existingValues = [],
    ) {
    }

    public function exists(string $table, array $criteria, ?string $connection = null): bool
    {
        $this->lastTable      = $table;
        $this->lastCriteria   = $criteria;
        $this->lastConnection = $connection;

        return $this->exists;
    }

    public function existingValues(LookupSpec $lookup, ?string $connection = null): ExistingValuesQueryInterface
    {
        return new TestExistingValuesQuery($this, $lookup, $connection);
    }

    /**
     * @return list<mixed>
     */
    public function fetchExistingValues(
        LookupSpec $lookup,
        ?string $connection,
        bool $warmup,
    ): array {
        if ($warmup) {
            $this->existingValuesWarmupFetches++;
        } else {
            $this->existingValuesCalls++;
        }

        $this->lastExistingTable            = $lookup->tableName();
        $this->lastExistingColumn           = $lookup->lookupColumnName();
        $this->lastExistingValues           = $lookup->lookupValues();
        $this->lastExistingCriteria         = $lookup->whereCriteria();
        $this->lastExistingConnection       = $connection;
        $this->lastExistingWarmupKeyColumns = $warmup ? $lookup->warmupKeyColumns() : [];

        return $this->existingValues;
    }

    public function unique(
        string $table,
        array $criteria,
        ?string $connection = null,
        ?string $ignoreColumn = null,
        mixed $ignoreValue = null,
    ): bool {
        $this->lastTable        = $table;
        $this->lastCriteria     = $criteria;
        $this->lastConnection   = $connection;
        $this->lastIgnoreColumn = $ignoreColumn;
        $this->lastIgnoreValue  = $ignoreValue;

        return $this->unique;
    }
}
