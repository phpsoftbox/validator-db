# ExistsValidation

Проверяет, что значение существует в базе данных.

Правило является декларативной specification. Выполнение делегируется
executor-слою (`ExistsRuleExecutor`) через `RuleExecutorRegistry`.

## Методы

### make
`make()` — статический фабричный конструктор.

### table
`table(string $table)` — таблица для проверки.

### column
`column(string $column)` — колонка для текущего значения.  
Если не указано, используется имя текущего поля.

### columns
`columns(string ...$columns)` — составной ключ. Значения берутся из `$data` по одноимённым ключам.  
Если ключ отсутствует, будет использовано значение `null` (в адаптере обычно превращается в `IS NULL`).

### all
`all()` — bulk-режим для поля-массива: правило одним запросом проверяет, что существуют все уникальные значения.
Пустой массив не считается ошибкой этого правила; если массив должен быть непустым, добавьте `ArrayValidation()->min(1)`.
Можно передать общий `LookupSpec`: `all($lookup)`.

### lookup
`lookup(LookupSpec $lookup)` — задаёт общую lookup-спецификацию для bulk-проверки.
Значения берутся из валидируемого поля массива, а spec задаёт таблицу, lookup-column,
fixed `where` и warmup key.

### warmup / warmupBy
`warmup()` — прогревает Database warmup API для bulk-проверки. Validator не зависит от ORM:
он прогревает строки на уровне Database по идентификаторному ключу.

`warmupBy(string ...$columns)` — явно задаёт колонки идентификаторного warmup key.
По умолчанию key строится из fixed criteria columns и lookup-column, поэтому для
`shipment_id = 123 AND product_id IN (...)` ключом будет `shipment_id + product_id`.
В явный `warmupBy()` должны входить lookup-column и все columns из fixed criteria.

### where / whereAll
`where(string $column, mixed $value)` и `whereAll(array $criteria)` — фиксированные условия проверки.
Например: `shipment_id = 123 AND product_id IN (...)`.

### connection
`connection(string $name)` — имя подключения.

### required / nullable
Доступны, см. required‑сценарии.

## Сообщения

Основные ключи:
`exists`, `exists_all`.

## Пример

```php
use PhpSoftBox\Validator\Db\Rule\ExistsValidation;
use PhpSoftBox\Validator\Db\Rule\Executor\ExistsRuleExecutor;
use PhpSoftBox\Validator\Rule\Executor\RuleExecutorRegistry;
use PhpSoftBox\Validator\Validator;

$registry = new RuleExecutorRegistry([
    [ExistsValidation::class, new ExistsRuleExecutor($adapter)],
]);

$validator = new Validator($registry);

$result = $validator->validate(
    data: ['email' => 'test@example.com'],
    rules: [
        'email' => [
            ExistsValidation::make()
                ->table('users')
                ->column('email'),
        ],
    ],
);
```

## Bulk-проверка массива

```php
use PhpSoftBox\Validator\Rule\ArrayValidation;
use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Validator\Db\Rule\ExistsValidation;

$result = $validator->validate(
    data: [
        'shipment_id' => 123,
        'product_ids' => [10, 20, 30],
    ],
    rules: [
        'product_ids' => [
            (new ArrayValidation())->min(1),
            ExistsValidation::make()
                ->all(
                    LookupSpec::forTable('shipment_products')
                        ->lookupColumn('product_id')
                        ->where('shipment_id', 123),
                )
                ->warmup(),
        ],
    ],
);
```

Если часть значений не найдена, violation содержит параметр `missing_values`.
