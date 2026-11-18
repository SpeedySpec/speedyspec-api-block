<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;
use SpeedySpec\Block\Infra\Memory\InMemoryDocumentRepository;

use function SpeedySpec\Block\ContractTests\dataSourceContract;
use function SpeedySpec\Block\ContractTests\documentRepositoryContract;

describe('InMemoryDocumentRepository', function () {
    documentRepositoryContract(fn () => new InMemoryDocumentRepository());
});

describe('ArrayDataSource', function () {
    dataSourceContract(
        fn () => new ArrayDataSource('acme/values', ['greeting' => 'hello'], ['type' => 'string']),
        fn () => new SubsetSchemaValidator(),
        ['key' => 'greeting'],
        ['key' => 'missing'],
    );
});

describe('InMemoryBlockTemplateRepository', function () {
    \SpeedySpec\Block\ContractTests\blockTemplateRepositoryContract(
        fn () => new \SpeedySpec\Block\Infra\Memory\InMemoryBlockTemplateRepository()
    );
});

test('the memory provider binds the template repository', function () {
    $container = new \SpeedySpec\Block\Domain\BlockServiceContainer();
    (new \SpeedySpec\Block\Infra\Memory\MemoryServiceProvider())->register($container);

    expect($container->get(\SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface::class))
        ->toBeInstanceOf(\SpeedySpec\Block\Infra\Memory\InMemoryBlockTemplateRepository::class);
});

describe('InMemoryRecordStore', function () {
    \SpeedySpec\Block\ContractTests\recordStoreContract(
        fn () => new \SpeedySpec\Block\Infra\Memory\InMemoryRecordStore()
    );
});

test('the memory provider binds the record store', function () {
    $container = new \SpeedySpec\Block\Domain\BlockServiceContainer();
    (new \SpeedySpec\Block\Infra\Memory\MemoryServiceProvider())->register($container);

    expect($container->get(\SpeedySpec\Block\Domain\Contracts\RecordStoreInterface::class))
        ->toBeInstanceOf(\SpeedySpec\Block\Infra\Memory\InMemoryRecordStore::class);
});
