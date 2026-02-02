<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Rule;

use PhpSoftBox\Validator\Rule\AbstractRule;
use PhpSoftBox\Validator\Rule\RuleSpecificationInterface;
use PhpSoftBox\Validator\ValidationEnum;
use RuntimeException;

use function array_merge;

/**
 * Проверяет уникальность значения в таблице базы данных.
 */
final class UniqueValidation extends AbstractRule implements RuleSpecificationInterface
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
     * Колонка, по которой игнорируем запись.
     */
    private ?string $ignoreColumn = null;
    /**
     * Значение для игнорируемой записи.
     */
    private mixed $ignoreValue = null;
    /**
     * Фиксированные условия для выборки (where).
     *
     * @var array<string, mixed>
     */
    private array $whereCriteria = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Таблица для проверки уникальности.
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
     * Добавить фиксированное условие к критериям уникальности.
     */
    public function where(string $column, mixed $value): self
    {
        $this->whereCriteria[$column] = $value;

        return $this;
    }

    /**
     * Добавить несколько фиксированных условий к критериям уникальности.
     *
     * @param array<string, mixed> $criteria
     */
    public function whereAll(array $criteria): self
    {
        $this->whereCriteria = array_merge($this->whereCriteria, $criteria);

        return $this;
    }

    /**
     * Игнорировать запись по идентификатору.
     */
    public function ignore(mixed $value, string $column = 'id'): self
    {
        $this->ignoreValue  = $value;
        $this->ignoreColumn = $column;

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
        throw new RuntimeException('UniqueValidation must be executed via rule executor.');
    }

    public function messages(): array
    {
        return [
            ValidationEnum::UNIQUE->value => 'Поле {field} должно быть уникальным в {table}.',
        ];
    }

    /**
     * Таблица для проверки уникальности.
     */
    public function tableName(): ?string
    {
        return $this->table;
    }

    /**
     * Колонка для одиночного условия.
     */
    public function columnName(): ?string
    {
        return $this->column;
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

    public function ignoreColumnName(): ?string
    {
        return $this->ignoreColumn;
    }

    public function ignoreValue(): mixed
    {
        return $this->ignoreValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function whereCriteria(): array
    {
        return $this->whereCriteria;
    }
}
