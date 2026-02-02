# PhpSoftBox Validator DB

Контракты, правила и executors для интеграции `phpsoftbox/validator` с базой данных.

## Установка

```bash
composer require phpsoftbox/validator-db
```

## Adapter

Для одиночных проверок реализуйте `DatabaseValidationAdapterInterface`:

```php
use PhpSoftBox\Validator\Db\Contracts\DatabaseValidationAdapterInterface;

final class DatabaseValidationAdapter implements DatabaseValidationAdapterInterface
{
    public function exists(string $table, array $criteria, ?string $connection = null): bool
    {
        // Проверка существования записи.
    }

    public function unique(
        string $table,
        array $criteria,
        ?string $connection = null,
        ?string $ignoreColumn = null,
        mixed $ignoreValue = null,
    ): bool {
        // Проверка уникальности записи.
    }
}
```

Для `ExistsValidation::all()` реализуйте `DatabaseBulkValidationAdapterInterface` и возвращайте
`ExistingValuesQueryInterface`. Bulk-запрос получает `LookupSpec`, содержащий таблицу, lookup-column,
список значений, дополнительные where-критерии и warmup key columns.

## Регистрация executors

```php
use PhpSoftBox\Validator\Db\Rule\Executor\ExistsRuleExecutor;
use PhpSoftBox\Validator\Db\Rule\Executor\UniqueRuleExecutor;
use PhpSoftBox\Validator\Db\Rule\ExistsValidation;
use PhpSoftBox\Validator\Db\Rule\UniqueValidation;
use PhpSoftBox\Validator\Rule\Executor\RuleExecutorRegistry;

$registry = new RuleExecutorRegistry([
    [ExistsValidation::class, new ExistsRuleExecutor($adapter)],
    [UniqueValidation::class, new UniqueRuleExecutor($adapter)],
]);
```

Передайте registry в `PhpSoftBox\Validator\Validator`.

## Exists

```php
ExistsValidation::make()
    ->table('users')
    ->column('email')
    ->where('active', true)
    ->connection('main');
```

Bulk-проверка всех значений:

```php
use PhpSoftBox\DatabaseLookup\LookupSpec;

ExistsValidation::make()
    ->table('products')
    ->column('id')
    ->all(
        LookupSpec::forTable('products')
            ->lookupColumn('id')
            ->keyColumns('tenant_id', 'id'),
    )
    ->where('tenant_id', 10)
    ->warmup();
```

## Unique

```php
UniqueValidation::make()
    ->table('users')
    ->column('email')
    ->where('tenant_id', 10)
    ->ignore($userId)
    ->connection('main');
```

## Breaking change

DB rules и executors перенесены из Validator. Старые namespace не поддерживаются:

```text
PhpSoftBox\Validator\Rule\ExistsValidation
    → PhpSoftBox\Validator\Db\Rule\ExistsValidation

PhpSoftBox\Validator\Rule\UniqueValidation
    → PhpSoftBox\Validator\Db\Rule\UniqueValidation

PhpSoftBox\Validator\Rule\Executor\Database\ExistsRuleExecutor
    → PhpSoftBox\Validator\Db\Rule\Executor\ExistsRuleExecutor

PhpSoftBox\Validator\Rule\Executor\Database\UniqueRuleExecutor
    → PhpSoftBox\Validator\Db\Rule\Executor\UniqueRuleExecutor
```

Подробные примеры находятся в `docs/exists-validation.md` и `docs/unique-validation.md`.

## Лицензия

MIT
