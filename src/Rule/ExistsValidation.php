<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Rule;

use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\Rule\RuleSpecificationInterface;
use PhpSoftBox\Validator\ValidationEnum;
use RuntimeException;

use function array_merge;

/**
 * Проверяет существование значения в таблице базы данных.
 */
final class ExistsValidation extends AbstractRule implements RuleSpecificationInterface
{
    /**
     * Имя таблицы для проверки.
     */
    private ?string $table = null;
    /**
     * Колонка для одиночного условия.
     */
    private ?string $column = null;
    /**
     * Колонки для составного условия.
     *
     * @var array<int, string>
     */
    private array $columns = [];
    /**
     * Имя подключения к БД.
     */
    private ?string $connection = null;
    /**
     * Проверять, что существуют все значения из массива.
     */
    private bool $all = false;
    /**
     * Фиксированные условия для выборки (where).
     *
     * @var array<string, mixed>
     */
    private array $whereCriteria = [];
    /**
     * Прогреть database warmup store для bulk-проверки.
     */
    private bool $warmup = false;
    /**
     * Колонки, образующие warmup key.
     *
     * @var list<string>
     */
    private array $warmupKeyColumns = [];
    /**
     * Общая lookup-спецификация для bulk-проверки и warmup.
     */
    private ?LookupSpec $lookup = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * Таблица для проверки существования.
     */
    public function table(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Колонка для текущего значения.
     */
    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    /**
     * Колонки для составного условия.
     */
    public function columns(string ...$columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Проверять массив значений одним запросом и требовать существования каждого уникального значения.
     */
    public function all(?LookupSpec $lookup = null): self
    {
        $this->all = true;

        if ($lookup !== null) {
            $this->lookup($lookup);
        }

        return $this;
    }

    /**
     * Задать lookup-спецификацию для bulk-проверки.
     */
    public function lookup(LookupSpec $lookup): self
    {
        $this->lookup = $lookup;

        return $this;
    }

    /**
     * Включить database warmup для bulk-проверки.
     */
    public function warmup(bool $enabled = true): self
    {
        $this->warmup = $enabled;

        return $this;
    }

    /**
     * Явно задать колонки, образующие warmup key.
     */
    public function warmupBy(string ...$columns): self
    {
        $this->warmupKeyColumns = $columns;

        return $this;
    }

    /**
     * Добавить фиксированное условие к критериям проверки.
     */
    public function where(string $column, mixed $value): self
    {
        $this->whereCriteria[$column] = $value;

        return $this;
    }

    /**
     * Добавить несколько фиксированных условий к критериям проверки.
     *
     * @param array<string, mixed> $criteria
     */
    public function whereAll(array $criteria): self
    {
        $this->whereCriteria = array_merge($this->whereCriteria, $criteria);

        return $this;
    }

    /**
     * Имя подключения к базе данных.
     */
    public function connection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function validate(mixed $value, string $field, bool $present, array $data): array
    {
        throw new RuntimeException('ExistsValidation must be executed via rule executor.');
    }

    public function messages(): array
    {
        return [
            ValidationEnum::EXISTS->value     => 'Поле {field} должно существовать в {table}.',
            ValidationEnum::EXISTS_ALL->value => 'Все значения поля {field} должны существовать в {table}.',
        ];
    }

    /**
     * Таблица для проверки существования.
     */
    public function tableName(): ?string
    {
        return $this->lookup?->tableName() ?? $this->table;
    }

    /**
     * Колонка для одиночного условия.
     */
    public function columnName(): ?string
    {
        return $this->column ?? $this->lookup?->lookupColumnName();
    }

    /**
     * @return array<int, string>
     */
    public function columnList(): array
    {
        return $this->columns;
    }

    /**
     * Имя подключения к БД.
     */
    public function connectionName(): ?string
    {
        return $this->connection;
    }

    public function shouldRequireAll(): bool
    {
        return $this->all;
    }

    /**
     * @return array<string, mixed>
     */
    public function whereCriteria(): array
    {
        return $this->whereCriteria;
    }

    public function shouldUseWarmup(): bool
    {
        return $this->warmup;
    }

    /**
     * @return list<string>
     */
    public function warmupKeyColumns(): array
    {
        return $this->warmupKeyColumns;
    }

    public function lookupSpec(): ?LookupSpec
    {
        return $this->lookup;
    }
}
