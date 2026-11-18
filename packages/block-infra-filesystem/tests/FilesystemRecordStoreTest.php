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
use SpeedySpec\Block\Infra\Filesystem\FilesystemRecordStore;
use SpeedySpec\Block\Infra\Filesystem\FilesystemServiceProvider;

use function SpeedySpec\Block\ContractTests\blockTemplateRepositoryContract;
use function SpeedySpec\Block\ContractTests\documentRepositoryContract;
use function SpeedySpec\Block\ContractTests\recordStoreContract;

$directories = [];
$fresh = function () use (&$directories): string {
    $directory = sys_get_temp_dir() . '/block-fs-' . bin2hex(random_bytes(8));
    $directories[] = $directory;

    return $directory;
};
$files = function (string $directory): array {
    if (!is_dir($directory))
    {
        return [];
    }

    $found = [];
    $walker = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($walker as $file)
    {
        $found[] = substr($file->getPathname(), strlen($directory) + 1);
    }

    sort($found);

    return $found;
};

afterAll(function () use (&$directories) {
    foreach ($directories as $directory)
    {
        if (!is_dir($directory))
        {
            continue;
        }

        $walker = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($walker as $file)
        {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
});

describe('FilesystemRecordStore', function () use ($fresh) {
    recordStoreContract(fn () => new FilesystemRecordStore($fresh()));
});

describe('StoredDocumentRepository over files', function () use ($fresh) {
    documentRepositoryContract(
        fn () => new StoredDocumentRepository(new FilesystemRecordStore($fresh()), new JsonDocumentSerializer())
    );
});

describe('StoredBlockTemplateRepository over files', function () use ($fresh) {
    blockTemplateRepositoryContract(fn () => new StoredBlockTemplateRepository(
        new FilesystemRecordStore($fresh()),
        new JsonBlockTemplateSerializer(new JsonDocumentSerializer()),
    ));
});

test('records outlive the store that wrote them', function () use ($fresh) {
    $directory = $fresh();
    $first = new FilesystemRecordStore($directory);
    $first->put('documents', 'a', 'one');
    $first->put('documents', 'b', 'two');
    $first->delete('documents', 'a');

    $second = new FilesystemRecordStore($directory);

    expect($second->get('documents', 'b'))->toBe('two')
        ->and($second->get('documents', 'a'))->toBeNull()
        ->and($second->keys('documents'))->toBe(['b']);
});

test('no key can write outside the store directory or leave stray files', function () use ($fresh, $files) {
    $root = $fresh();
    mkdir($root);
    $store = new FilesystemRecordStore($root . '/store');
    $store->put('documents', '../../escape', 'x');
    $store->put('documents', '/etc/passwd', 'y');
    $store->put('documents', "nul\0byte", 'z');

    $written = $files($root);

    expect($store->get('documents', "nul\0byte"))->toBe('z')
        ->and(count($written))->toBe(5)
        ->and(array_filter($written, fn (string $path) => !str_starts_with($path, 'store/documents/')))->toBe([]);
});

test('reading never creates anything on disk', function () use ($fresh, $files) {
    $directory = $fresh();
    $store = new FilesystemRecordStore($directory);

    expect($store->get('documents', 'a'))->toBeNull()
        ->and($store->keys('documents'))->toBe([])
        ->and($files($directory))->toBe([]);
});

test('a directory that cannot be written is reported as a storage failure', function () use ($fresh) {
    $root = $fresh();
    mkdir($root);
    file_put_contents($root . '/blocked', 'a file where the directory should be');
    $store = new FilesystemRecordStore($root . '/blocked');

    expect(fn () => $store->put('documents', 'a', 'x'))->toThrow(StorageException::class);
});

test('a document saved through the providers is there for the next request', function () use ($fresh) {
    $directory = $fresh();
    $container = function () use ($directory): BlockServiceContainer {
        $c = new BlockServiceContainer();
        (new AppServiceProvider())->register($c);
        (new StorageServiceProvider())->register($c);
        (new FilesystemServiceProvider($directory))->register($c);

        return $c;
    };
    $document = new Document('home', 'page', new BlockTree(new Block('a', BlockName::fromString('acme/box'))));

    $container()->get(DocumentRepositoryInterface::class)->save($document);

    expect($container()->get(DocumentRepositoryInterface::class)->find('home'))->toEqual($document)
        ->and($container()->get(RecordStoreInterface::class))->toBeInstanceOf(FilesystemRecordStore::class);
});
