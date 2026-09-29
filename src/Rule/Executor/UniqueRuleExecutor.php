<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Rule\Executor;

use InvalidArgumentException;
use PhpSoftBox\Validator\Db\Contracts\DatabaseValidationAdapterInterface;
use PhpSoftBox\Validator\Db\Rule\UniqueValidation;
use PhpSoftBox\Validator\Db\Support\FieldColumn;
use PhpSoftBox\Validator\Rule\Executor\RuleExecutorInterface;
use PhpSoftBox\Validator\Rule\RuleSpecificationInterface;
use PhpSoftBox\Validator\Support\DataPath;
use PhpSoftBox\Validator\ValidationEnum;
use PhpSoftBox\Validator\ValidationViolation;

use function array_keys;
use function array_merge;

final readonly class UniqueRuleExecutor implements RuleExecutorInterface
{
    public function __construct(
        private DatabaseValidationAdapterInterface $adapter,
    ) {
    }

    public function supports(RuleSpecificationInterface $rule): bool
    {
        return $rule instanceof UniqueValidation;
    }

    public function validate(
        RuleSpecificationInterface $rule,
        mixed $value,
        string $field,
        bool $present,
        array $data,
    ): array {
        if (!$rule instanceof UniqueValidation) {
            throw new InvalidArgumentException('UniqueRuleExecutor supports only UniqueValidation.');
        }

        $table = $rule->tableName();
        if ($table === null || $table === '') {
            throw new InvalidArgumentException('Для правила unique требуется таблица.');
        }

        $criteria = $this->buildCriteria($rule, $value, $field, $data);
        if ($criteria === []) {
            return [new ValidationViolation(ValidationEnum::UNIQUE->value, ['table' => $table])];
        }

        if (
            !$this->adapter->unique(
                $table,
                $criteria,
                $rule->connectionName(),
                $rule->ignoreColumnName(),
                $rule->ignoreValue(),
            )
        ) {
            return [new ValidationViolation(ValidationEnum::UNIQUE->value, [
                'table'      => $table,
                'columns'    => array_keys($criteria),
                'connection' => $rule->connectionName(),
            ])];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCriteria(UniqueValidation $rule, mixed $value, string $field, array $data): array
    {
        $columns = $rule->columnList();

        $criteria = [];
        if ($columns !== []) {
            foreach ($columns as $column) {
                $criteria[FieldColumn::assert($column)] = DataPath::get($data, $column);
            }
        } else {
            $column            = FieldColumn::assert($rule->columnName() ?? FieldColumn::fromField($field), $field);
            $criteria[$column] = $value;
        }

        if ($rule->whereCriteria() !== []) {
            $criteria = array_merge($criteria, $rule->whereCriteria());
        }

        return $criteria;
    }
}
