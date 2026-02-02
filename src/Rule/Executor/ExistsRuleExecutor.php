<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Rule\Executor;

use InvalidArgumentException;
use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Validator\Db\Contracts\DatabaseBulkValidationAdapterInterface;
use PhpSoftBox\Validator\Db\Contracts\DatabaseValidationAdapterInterface;
use PhpSoftBox\Validator\Db\Rule\ExistsValidation;
use PhpSoftBox\Validator\Rule\Executor\RuleExecutorInterface;
use PhpSoftBox\Validator\Rule\RuleSpecificationInterface;
use PhpSoftBox\Validator\Support\DataPath;
use PhpSoftBox\Validator\ValidationEnum;
use PhpSoftBox\Validator\ValidationViolation;

use function array_keys;
use function array_merge;
use function array_values;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function serialize;

final readonly class ExistsRuleExecutor implements RuleExecutorInterface
{
    public function __construct(
        private DatabaseValidationAdapterInterface $adapter,
    ) {
    }

    public function supports(RuleSpecificationInterface $rule): bool
    {
        return $rule instanceof ExistsValidation;
    }

    public function validate(
        RuleSpecificationInterface $rule,
        mixed $value,
        string $field,
        bool $present,
        array $data,
    ): array {
        if (!$rule instanceof ExistsValidation) {
            throw new InvalidArgumentException('ExistsRuleExecutor supports only ExistsValidation.');
        }

        $table = $rule->tableName();
        if ($table === null || $table === '') {
            throw new InvalidArgumentException('Для правила exists требуется таблица.');
        }

        if ($rule->shouldRequireAll()) {
            return $this->validateAll($rule, $value, $field, $table);
        }

        $criteria = $this->buildCriteria($rule, $value, $field, $data);
        if ($criteria === []) {
            return [new ValidationViolation(ValidationEnum::EXISTS->value, ['table' => $table])];
        }

        if (!$this->adapter->exists($table, $criteria, $rule->connectionName())) {
            return [new ValidationViolation(ValidationEnum::EXISTS->value, [
                'table'      => $table,
                'columns'    => array_keys($criteria),
                'connection' => $rule->connectionName(),
            ])];
        }

        return [];
    }

    /**
     * @return list<ValidationViolation>
     */
    private function validateAll(ExistsValidation $rule, mixed $value, string $field, string $table): array
    {
        if (!is_array($value)) {
            return [new ValidationViolation(ValidationEnum::EXISTS_ALL->value, [
                'table'          => $table,
                'column'         => $rule->columnName() ?? $field,
                'connection'     => $rule->connectionName(),
                'missing_values' => [],
            ])];
        }

        $uniqueValues = $this->uniqueValues($value);
        if ($uniqueValues === []) {
            return [];
        }

        $column = $rule->columnName() ?? $field;
        if (!$this->adapter instanceof DatabaseBulkValidationAdapterInterface) {
            throw new InvalidArgumentException('Для правила exists_all требуется bulk database validation adapter.');
        }

        $lookup              = $this->buildLookup($rule, $table, $column, array_values($uniqueValues));
        $existingValuesQuery = $this->adapter->existingValues($lookup, $rule->connectionName());

        if ($rule->shouldUseWarmup()) {
            $existingValuesQuery = $existingValuesQuery->warmup();
        }

        $found = $existingValuesQuery->fetch();

        $foundKeys = [];
        foreach ($found as $foundValue) {
            $foundKeys[$this->valueKey($foundValue)] = true;
        }

        $missing = [];
        foreach ($uniqueValues as $key => $inputValue) {
            if (!isset($foundKeys[$key])) {
                $missing[] = $inputValue;
            }
        }

        if ($missing !== []) {
            return [new ValidationViolation(ValidationEnum::EXISTS_ALL->value, [
                'table'          => $table,
                'column'         => $lookup->lookupColumnName(),
                'columns'        => [$lookup->lookupColumnName()],
                'connection'     => $rule->connectionName(),
                'missing_values' => $missing,
            ])];
        }

        return [];
    }

    /**
     * @param list<mixed> $values
     */
    private function buildLookup(ExistsValidation $rule, string $table, string $column, array $values): LookupSpec
    {
        $lookup = $rule->lookupSpec() ?? LookupSpec::forTable($table)->lookupColumn($column);
        $lookup = $lookup->values($values);

        if ($rule->whereCriteria() !== []) {
            $lookup = $lookup->whereAll($rule->whereCriteria());
        }

        if ($rule->warmupKeyColumns() !== []) {
            $lookup = $lookup->keyColumns(...$rule->warmupKeyColumns());
        }

        return $lookup;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCriteria(ExistsValidation $rule, mixed $value, string $field, array $data): array
    {
        $columns = $rule->columnList();

        $criteria = [];
        if ($columns !== []) {
            foreach ($columns as $column) {
                $criteria[$column] = DataPath::get($data, $column);
            }
        } else {
            $column            = $rule->columnName() ?? $field;
            $criteria[$column] = $value;
        }

        if ($rule->whereCriteria() !== []) {
            $criteria = array_merge($criteria, $rule->whereCriteria());
        }

        return $criteria;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function uniqueValues(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $unique[$this->valueKey($value)] ??= $value;
        }

        return $unique;
    }

    private function valueKey(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }

        if (is_bool($value)) {
            return 'bool:' . ($value ? '1' : '0');
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return 'scalar:' . (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return 'scalar:' . (string) $value;
        }

        return 'complex:' . serialize($value);
    }
}
