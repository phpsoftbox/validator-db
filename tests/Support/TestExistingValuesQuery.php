<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Tests\Support;

use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Validator\Db\Contracts\ExistingValuesQueryInterface;

final readonly class TestExistingValuesQuery implements ExistingValuesQueryInterface
{
    public function __construct(
        private TestDatabaseAdapter $adapter,
        private LookupSpec $lookup,
        private ?string $connection,
        private bool $warmup = false,
    ) {
    }

    public function warmup(): ExistingValuesQueryInterface
    {
        return new self(
            $this->adapter,
            $this->lookup,
            $this->connection,
            warmup: true,
        );
    }

    public function fetch(): array
    {
        return $this->adapter->fetchExistingValues(
            $this->lookup,
            $this->connection,
            $this->warmup,
        );
    }
}
