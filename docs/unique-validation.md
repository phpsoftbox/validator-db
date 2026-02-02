# UniqueValidation

Проверяет, что значение уникально в базе данных.

Правило является декларативной specification. Выполнение делегируется
executor-слою (`UniqueRuleExecutor`) через `RuleExecutorRegistry`.

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

### ignore
`ignore(mixed $value, string $column = 'id')` — игнорировать запись с указанным идентификатором.

### connection
`connection(string $name)` — имя подключения.

### required / nullable
Доступны, см. required‑сценарии.

## Сообщения

Основные ключи:
`unique`.

## Пример

```php
use PhpSoftBox\Validator\Db\Rule\UniqueValidation;
use PhpSoftBox\Validator\Db\Rule\Executor\UniqueRuleExecutor;
use PhpSoftBox\Validator\Rule\Executor\RuleExecutorRegistry;
use PhpSoftBox\Validator\Validator;

$registry = new RuleExecutorRegistry([
    [UniqueValidation::class, new UniqueRuleExecutor($adapter)],
]);

$validator = new Validator($registry);

$result = $validator->validate(
    data: ['email' => 'test@example.com'],
    rules: [
        'email' => [
            UniqueValidation::make()
                ->table('users')
                ->column('email')
                ->ignore(10),
        ],
    ],
);
```
