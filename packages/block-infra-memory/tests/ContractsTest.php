<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;
use SpeedySpec\Block\Infra\Memory\InMemoryDocumentRepository;

use function SpeedySpec\Block\ContractTests\dataSourceContract;
use function SpeedySpec\Block\ContractTests\documentRepositoryContract;

describe('InMemoryDocumentRepository', function () {
    documentRepositoryContract(fn () => new InMemoryDocumentRepository(), 2);
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

test('the memory provider only fills gaps, so real storage wins in any registration order', function (array $order) {
    $real = new \SpeedySpec\Block\Infra\Memory\InMemoryRecordStore();
    $providers = [
        'app' => fn ($c) => (new \SpeedySpec\Block\App\AppServiceProvider())->register($c),
        'memory' => fn ($c) => (new \SpeedySpec\Block\Infra\Memory\MemoryServiceProvider())->register($c),
        'storage' => fn ($c) => (new \SpeedySpec\Block\App\Storage\StorageServiceProvider())->register($c),
        'real' => fn ($c) => $c->add(\SpeedySpec\Block\Domain\Contracts\RecordStoreInterface::class, fn () => $real),
    ];
    $container = new \SpeedySpec\Block\Domain\BlockServiceContainer();

    foreach ($order as $name)
    {
        $providers[$name]($container);
    }

    expect($container->get(\SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface::class))
        ->toBeInstanceOf(\SpeedySpec\Block\App\Storage\StoredDocumentRepository::class)
        ->and($container->get(\SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface::class))
        ->toBeInstanceOf(\SpeedySpec\Block\App\Storage\StoredBlockTemplateRepository::class)
        ->and($container->get(\SpeedySpec\Block\Domain\Contracts\RecordStoreInterface::class))->toBe($real);
})->with([
    [['app', 'memory', 'storage', 'real']],
    [['real', 'storage', 'app', 'memory']],
    [['app', 'storage', 'real', 'memory']],
    [['app', 'storage', 'memory', 'real']],
    [['app', 'real', 'memory', 'storage']],
    [['memory', 'app', 'real', 'storage']],
]);
