<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Serialization\JsonBlockTemplateSerializer;
use SpeedySpec\Block\App\Serialization\JsonDocumentSerializer;
use SpeedySpec\Block\App\Storage\StorageServiceProvider;
use SpeedySpec\Block\App\Storage\StoredBlockTemplateRepository;
use SpeedySpec\Block\App\Storage\StoredDocumentRepository;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Infra\Pdo\PdoRecordStore;
use SpeedySpec\Block\Infra\Pdo\PdoServiceProvider;

use function SpeedySpec\Block\ContractTests\blockTemplateRepositoryContract;
use function SpeedySpec\Block\ContractTests\documentRepositoryContract;
use function SpeedySpec\Block\ContractTests\recordStoreContract;

$store = function (?PDO $pdo = null): PdoRecordStore {
    $store = new PdoRecordStore($pdo ?? new PDO('sqlite::memory:'));
    $store->install();

    return $store;
};

describe('PdoRecordStore', function () use ($store) {
    recordStoreContract(fn () => $store());
});

describe('PdoRecordStore on a connection that reports errors silently', function () use ($store) {
    recordStoreContract(function () use ($store) {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        return $store($pdo);
    });
});

describe('StoredDocumentRepository over a database', function () use ($store) {
    documentRepositoryContract(fn () => new StoredDocumentRepository($store(), new JsonDocumentSerializer()));
});

describe('StoredBlockTemplateRepository over a database', function () use ($store) {
    blockTemplateRepositoryContract(fn () => new StoredBlockTemplateRepository(
        $store(),
        new JsonBlockTemplateSerializer(new JsonDocumentSerializer()),
    ));
});

test('records outlive the store that wrote them, and installing twice loses nothing', function () use ($store) {
    $pdo = new PDO('sqlite::memory:');
    $first = $store($pdo);
    $first->put('documents', 'a', 'one');
    $first->put('documents', 'b', 'two');
    $first->delete('documents', 'a');

    $second = $store($pdo);

    expect($second->get('documents', 'b'))->toBe('two')
        ->and($second->keys('documents'))->toBe(['b']);
});

test('a missing table is reported as a storage failure, whatever the connection error mode', function (int $mode) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
    $store = new PdoRecordStore($pdo);

    expect(fn () => $store->get('documents', 'a'))->toThrow(StorageException::class)
        ->and(fn () => $store->put('documents', 'a', 'x'))->toThrow(StorageException::class)
        ->and(fn () => $store->keys('documents'))->toThrow(StorageException::class)
        ->and($pdo->getAttribute(PDO::ATTR_ERRMODE))->toBe($mode)
        ->and($pdo->inTransaction())->toBeFalse();
})->with([PDO::ERRMODE_EXCEPTION, PDO::ERRMODE_SILENT]);

test('a table name that is not a plain identifier is refused', function (string $table) {
    expect(fn () => new PdoRecordStore(new PDO('sqlite::memory:'), $table))->toThrow(InvalidArgumentException::class);
})->with(['', 'records; DROP TABLE x', 'a b', '1abc', 'a"b', str_repeat('a', 65)]);

test('two tables in one database keep their records apart', function () {
    $pdo = new PDO('sqlite::memory:');
    $one = new PdoRecordStore($pdo, 'site_one_blocks');
    $two = new PdoRecordStore($pdo, 'site_two_blocks');
    $one->install();
    $two->install();
    $one->put('documents', 'a', 'one');

    expect($two->get('documents', 'a'))->toBeNull()->and($one->get('documents', 'a'))->toBe('one');
});

test('the store joins a transaction the host already opened instead of ending it', function () use ($store) {
    $pdo = new PDO('sqlite::memory:');
    $records = $store($pdo);
    $pdo->beginTransaction();
    $records->put('documents', 'a', 'one');

    expect($pdo->inTransaction())->toBeTrue();

    $pdo->rollBack();

    expect($records->get('documents', 'a'))->toBeNull();
});

test('a document saved through the providers is there for the next request', function () {
    $pdo = new PDO('sqlite::memory:');
    (new PdoRecordStore($pdo))->install();
    $container = function () use ($pdo): BlockServiceContainer {
        $c = new BlockServiceContainer();
        (new AppServiceProvider())->register($c);
        (new StorageServiceProvider())->register($c);
        (new PdoServiceProvider($pdo))->register($c);

        return $c;
    };
    $document = new Document('home', 'page', new BlockTree(new Block('a', BlockName::fromString('acme/box'))));

    $container()->get(DocumentRepositoryInterface::class)->save($document);

    expect($container()->get(DocumentRepositoryInterface::class)->find('home'))->toEqual($document)
        ->and($container()->get(RecordStoreInterface::class))->toBeInstanceOf(PdoRecordStore::class);
});
