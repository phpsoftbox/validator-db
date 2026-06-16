<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Contracts;

interface ExistingValuesQueryInterface
{
    /**
     * Включает прогрев database warmup для найденных строк.
     */
    public function warmup(): self;

    /**
     * Выполняет запрос и возвращает найденные значения колонки.
     *
     * @return list<mixed>
     */
    public function fetch(): array;
}
