# PhpSoftBox Validator DB

Контракты для интеграции валидатора с базой данных.

## Установка
```bash
composer require phpsoftbox/validator-db
```

## Использование

```php
use PhpSoftBox\Validator\Db\Contracts\DatabaseBulkValidationAdapterInterface;
use PhpSoftBox\Validator\Db\Contracts\ExistingValuesQueryInterface;

final class MyAdapter implements DatabaseBulkValidationAdapterInterface
{
    public function exists(string $table, array $criteria, ?string $connection = null): bool
    {
        // ...
    }

    public function existingValues(
        string $table,
        string $column,
        array $values,
        array $criteria = [],
        ?string $connection = null,
    ): ExistingValuesQueryInterface {
        // Возвращает query object.
        // Обычное выполнение: ->fetch()
        // С прогревом: ->warmup('shipment_id', 'product_id')->fetch()
    }

    public function unique(
        string $table,
        array $criteria,
        ?string $connection = null,
        ?string $ignoreColumn = null,
        mixed $ignoreValue = null,
    ): bool {
        // ...
    }
}
```

Если adapter не поддерживает bulk-проверки, он может реализовать только
`DatabaseValidationAdapterInterface`. Для правил вроде `ExistsValidation::all()`
нужен `DatabaseBulkValidationAdapterInterface`.

## Лицензия
MIT
