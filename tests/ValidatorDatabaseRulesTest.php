<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Tests;

use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Validator\Db\Contracts\DatabaseBulkValidationAdapterInterface;
use PhpSoftBox\Validator\Db\Rule\Executor\ExistsRuleExecutor;
use PhpSoftBox\Validator\Db\Rule\Executor\UniqueRuleExecutor;
use PhpSoftBox\Validator\Db\Rule\ExistsValidation;
use PhpSoftBox\Validator\Db\Rule\UniqueValidation;
use PhpSoftBox\Validator\Db\Tests\Support\TestDatabaseAdapter;
use PhpSoftBox\Validator\Exception\RuleExecutorNotFoundException;
use PhpSoftBox\Validator\Rule\ArrayValidation;
use PhpSoftBox\Validator\Rule\Executor\RuleExecutorRegistry;
use PhpSoftBox\Validator\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
#[CoversClass(ExistsValidation::class)]
#[CoversClass(UniqueValidation::class)]
#[CoversClass(ExistsRuleExecutor::class)]
#[CoversClass(UniqueRuleExecutor::class)]
#[CoversClass(RuleExecutorRegistry::class)]
final class ValidatorDatabaseRulesTest extends TestCase
{
    /**
     * Проверяет правило exists.
     */
    #[Test]
    public function existsRuleFailsWhenMissing(): void
    {
        $adapter = $this->databaseAdapter(exists: false, unique: true);

        $validator = $this->validator($adapter);

        $rules = [
            'email' => [ExistsValidation::make()->table('users')->column('email')],
        ];

        $result = $validator->validate(['email' => 'test@example.com'], $rules);

        self::assertSame(['Поле email должно существовать в users.'], $result->errorBag()->get('email'));
        self::assertSame('users', $adapter->lastTable);
        self::assertSame(['email' => 'test@example.com'], $adapter->lastCriteria);
    }

    /**
     * Проверяет правило unique.
     */
    #[Test]
    public function uniqueRuleFailsWhenNotUnique(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: false);

        $validator = $this->validator($adapter);

        $rules = [
            'email' => [UniqueValidation::make()->table('users')->column('email')],
        ];

        $result = $validator->validate(['email' => 'test@example.com'], $rules);

        self::assertSame(['Поле email должно быть уникальным в users.'], $result->errorBag()->get('email'));
    }

    /**
     * Проверяет использование составных колонок и connection.
     */
    #[Test]
    public function existsUsesCompositeColumnsAndConnection(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true);

        $validator = $this->validator($adapter);

        $rules = [
            'email' => [
                ExistsValidation::make()
                    ->table('accounts')
                    ->columns('email', 'account_id')
                    ->connection('read'),
            ],
        ];

        $validator->validate([
                    'email'      => 'a@b.com',
                    'account_id' => 5,
                ], $rules);

        self::assertSame('accounts', $adapter->lastTable);
        self::assertSame(['email' => 'a@b.com', 'account_id' => 5], $adapter->lastCriteria);
        self::assertSame('read', $adapter->lastConnection);
    }

    /**
     * Проверяет bulk-режим exists:all, когда все значения найдены.
     */
    #[Test]
    public function existsAllPassesWhenAllValuesExist(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10, 20, 30]);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => [10, 20, 30]],
            ['product_ids' => [ExistsValidation::make()->table('products')->column('id')->all()]],
        );

        self::assertFalse($result->hasErrors());
        self::assertSame('products', $adapter->lastExistingTable);
        self::assertSame('id', $adapter->lastExistingColumn);
        self::assertSame([10, 20, 30], $adapter->lastExistingValues);
        self::assertSame(1, $adapter->existingValuesCalls);
    }

    /**
     * Проверяет, что bulk-режим exists:all возвращает ошибку на поле массива и список отсутствующих значений.
     */
    #[Test]
    public function existsAllFailsWhenSomeValuesAreMissing(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10, 30]);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => [10, 20, 30, 40]],
            ['product_ids' => [ExistsValidation::make()->table('products')->column('id')->all()]],
            ['product_ids' => ['exists_all' => 'Missing: {missing_values}']],
        );

        self::assertSame(['Missing: [20,40]'], $result->errorBag()->get('product_ids'));
        self::assertSame(1, $adapter->existingValuesCalls);
    }

    /**
     * Проверяет, что пустой массив валидируется правилом массива, а exists:all не ходит в БД.
     */
    #[Test]
    public function existsAllDoesNotRejectEmptyArrayByItself(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => []],
            [
                'product_ids' => [
                    new ArrayValidation()->min(1),
                    ExistsValidation::make()->table('products')->column('id')->all(),
                ],
            ],
        );

        self::assertSame(
            ['Количество элементов в product_ids должно быть не меньше 1.'],
            $result->errorBag()->get('product_ids'),
        );
        self::assertSame(0, $adapter->existingValuesCalls);
    }

    /**
     * Проверяет, что дубликаты нормализуются до обращения к adapter-у.
     */
    #[Test]
    public function existsAllNormalizesDuplicateValues(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10, 20]);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => [10, 10, 20, 20]],
            ['product_ids' => [ExistsValidation::make()->table('products')->column('id')->all()]],
        );

        self::assertFalse($result->hasErrors());
        self::assertSame([10, 20], $adapter->lastExistingValues);
        self::assertSame(1, $adapter->existingValuesCalls);
    }

    /**
     * Проверяет, что warmup bulk-режим включает warmup на query object.
     */
    #[Test]
    public function existsAllWarmupUsesWarmupQueryOption(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10, 20]);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => [10, 20]],
            [
                'product_ids' => [
                    ExistsValidation::make()
                        ->table('products')
                        ->column('id')
                        ->all()
                        ->warmup(),
                ],
            ],
        );

        self::assertFalse($result->hasErrors());
        self::assertSame(0, $adapter->existingValuesCalls);
        self::assertSame(1, $adapter->existingValuesWarmupFetches);
        self::assertSame(['id'], $adapter->lastExistingWarmupKeyColumns);
    }

    /**
     * Проверяет, что warmup bulk-режим передаёт явные колонки warmup key.
     */
    #[Test]
    public function existsAllWarmupPassesExplicitKeyColumns(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10, 20]);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => [10, 20]],
            [
                'product_ids' => [
                    ExistsValidation::make()
                        ->table('shipment_products')
                        ->column('product_id')
                        ->where('shipment_id', 123)
                        ->all()
                        ->warmup()
                        ->warmupBy('shipment_id', 'product_id'),
                ],
            ],
        );

        self::assertFalse($result->hasErrors());
        self::assertSame(['shipment_id' => 123], $adapter->lastExistingCriteria);
        self::assertSame(['shipment_id', 'product_id'], $adapter->lastExistingWarmupKeyColumns);
    }

    /**
     * Проверяет, что warmup bulk-режим с where использует default key из criteria и lookup-column.
     */
    #[Test]
    public function existsAllWarmupWithWhereUsesDefaultLookupKey(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10]);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => [10]],
            [
                'product_ids' => [
                    ExistsValidation::make()
                        ->table('shipment_products')
                        ->column('product_id')
                        ->where('shipment_id', 123)
                        ->all()
                        ->warmup(),
                ],
            ],
        );

        self::assertFalse($result->hasErrors());
        self::assertSame(['shipment_id' => 123], $adapter->lastExistingCriteria);
        self::assertSame(['shipment_id', 'product_id'], $adapter->lastExistingWarmupKeyColumns);
    }

    /**
     * Проверяет, что bulk-режим принимает общий LookupSpec.
     */
    #[Test]
    public function existsAllAcceptsLookupSpec(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10, 20]);

        $validator = $this->validator($adapter);

        $result = $validator->validate(
            ['product_ids' => [10, 20]],
            [
                'product_ids' => [
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

        self::assertFalse($result->hasErrors());
        self::assertSame('shipment_products', $adapter->lastExistingTable);
        self::assertSame('product_id', $adapter->lastExistingColumn);
        self::assertSame([10, 20], $adapter->lastExistingValues);
        self::assertSame(['shipment_id' => 123], $adapter->lastExistingCriteria);
        self::assertSame(['shipment_id', 'product_id'], $adapter->lastExistingWarmupKeyColumns);
    }

    /**
     * Проверяет передачу кастомного connection в bulk-режиме.
     */
    #[Test]
    public function existsAllUsesCustomConnection(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10]);

        $validator = $this->validator($adapter);

        $validator->validate(
            ['product_ids' => [10]],
            [
                'product_ids' => [
                    ExistsValidation::make()
                        ->table('products')
                        ->column('id')
                        ->connection('tenant')
                        ->all(),
                ],
            ],
        );

        self::assertSame('tenant', $adapter->lastExistingConnection);
    }

    /**
     * Проверяет фиксированные where-условия в bulk-режиме.
     */
    #[Test]
    public function existsAllUsesAdditionalWhereCriteria(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true, existingValues: [10]);

        $validator = $this->validator($adapter);

        $validator->validate(
            ['product_ids' => [10]],
            [
                'product_ids' => [
                    ExistsValidation::make()
                        ->table('shipment_products')
                        ->column('product_id')
                        ->where('shipment_id', 123)
                        ->all(),
                ],
            ],
        );

        self::assertSame(['shipment_id' => 123], $adapter->lastExistingCriteria);
    }

    /**
     * Проверяет фиксированные where-условия для обычного exists.
     */
    #[Test]
    public function existsWhereAddsFixedCriteria(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true);

        $validator = $this->validator($adapter);

        $validator->validate(
            ['product_id' => 10],
            [
                'product_id' => [
                    ExistsValidation::make()
                        ->table('shipment_products')
                        ->column('product_id')
                        ->where('shipment_id', 123),
                ],
            ],
        );

        self::assertSame(
            ['product_id' => 10, 'shipment_id' => 123],
            $adapter->lastCriteria,
        );
    }

    /**
     * Проверяет ignore в unique.
     */
    #[Test]
    public function uniqueIgnorePassesValue(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true);

        $validator = $this->validator($adapter);

        $rules = [
            'email' => [
                UniqueValidation::make()
                    ->table('users')
                    ->column('email')
                    ->ignore(10),
            ],
        ];

        $validator->validate(['email' => 'test@example.com'], $rules);

        self::assertSame('id', $adapter->lastIgnoreColumn);
        self::assertSame(10, $adapter->lastIgnoreValue);
    }

    /**
     * Проверяет фиксированные where-условия в unique.
     */
    #[Test]
    public function uniqueWhereAddsFixedCriteria(): void
    {
        $adapter = $this->databaseAdapter(exists: true, unique: true);

        $validator = $this->validator($adapter);

        $rules = [
            'phone' => [
                UniqueValidation::make()
                    ->table('users')
                    ->column('phone')
                    ->where('is_phone_confirmed', 1),
            ],
        ];

        $validator->validate(['phone' => '+79990001122'], $rules);

        self::assertSame(
            ['phone' => '+79990001122', 'is_phone_confirmed' => 1],
            $adapter->lastCriteria,
        );
    }

    /**
     * Проверяет ошибку при отсутствии registry executor-ов для specification-правил.
     */
    #[Test]
    public function throwsWhenSpecificationRuleExecutorRegistryIsNotConfigured(): void
    {
        $validator = new Validator();

        $this->expectException(RuleExecutorNotFoundException::class);
        $this->expectExceptionMessage('Rule executor registry is not configured');

        $validator->validate(
            ['email' => 'test@example.com'],
            ['email' => [ExistsValidation::make()->table('users')->column('email')]],
        );
    }

    /**
     * @param list<mixed> $existingValues
     */
    private function databaseAdapter(bool $exists, bool $unique, array $existingValues = []): TestDatabaseAdapter
    {
        return new TestDatabaseAdapter($exists, $unique, $existingValues);
    }

    private function validator(DatabaseBulkValidationAdapterInterface $adapter): Validator
    {
        $registry = new RuleExecutorRegistry([
            [ExistsValidation::class, new ExistsRuleExecutor($adapter)],
            [UniqueValidation::class, new UniqueRuleExecutor($adapter)],
        ]);

        return new Validator($registry);
    }
}
