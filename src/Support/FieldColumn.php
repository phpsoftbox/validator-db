<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Support;

use InvalidArgumentException;

use function array_filter;
use function array_values;
use function ctype_digit;
use function end;
use function explode;
use function preg_match;

/**
 * Колонка по умолчанию для правил exists/unique: последний нечисловой сегмент пути поля (`user.email` → `email`,
 * `items.0.product_id` → `product_id`). Имя попадает в текст SQL, поэтому допускается только идентификатор.
 */
final class FieldColumn
{
    public static function fromField(string $field): string
    {
        $segments = array_values(array_filter(
            explode('.', $field),
            static fn (string $segment): bool => $segment !== '' && $segment !== '*' && !ctype_digit($segment),
        ));

        $column = $segments === [] ? '' : (string) end($segments);

        return self::assert($column, $field);
    }

    public static function assert(string $column, string $field = ''): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column) !== 1) {
            throw new InvalidArgumentException(
                'Колонка "' . $column . '"' . ($field !== '' ? ' для поля "' . $field . '"' : '')
                . ' не является идентификатором; укажите её через column().',
            );
        }

        return $column;
    }
}
