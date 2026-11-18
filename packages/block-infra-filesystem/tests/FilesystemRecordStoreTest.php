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

test('an empty directory is refused rather than taken for the root of the disk', function (string $directory) {
    expect(fn () => new FilesystemRecordStore($directory))->toThrow(InvalidArgumentException::class);
})->with(['', '/', '  ']);

test('a collection directory that appears while a writer is creating it is not an error', function () use ($fresh) {
    $directory = $fresh();
    $store = new class($directory) extends FilesystemRecordStore {
        protected function makeDirectory(string $directory): bool
        {
            // Another writer gets there first; this one's mkdir then fails with "File exists".
            mkdir($directory, 0775, true);

            return parent::makeDirectory($directory);
        }
    };

    $store->put('documents', 'a', 'one');

    expect($store->get('documents', 'a'))->toBe('one');
});

test('storage that cannot be read is reported, never mistaken for empty', function () use ($fresh) {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0)
    {
        $this->markTestSkipped('The superuser can read anything.');
    }

    $directory = $fresh();
    $store = new FilesystemRecordStore($directory);
    $store->put('documents', 'a', 'one');
    chmod($directory . '/documents', 0000);

    try
    {
        expect(fn () => $store->get('documents', 'a'))->toThrow(StorageException::class)
            ->and(fn () => $store->keys('documents'))->toThrow(StorageException::class)
            ->and(fn () => $store->delete('documents', 'a'))->toThrow(StorageException::class);
    }
    finally
    {
        chmod($directory . '/documents', 0775);
    }

    $file = $fresh();
    file_put_contents($file, 'a file where the directory should be');
    $blocked = new FilesystemRecordStore($file . '/inside');

    expect(fn () => $blocked->get('documents', 'a'))->toThrow(StorageException::class)
        ->and(fn () => $blocked->keys('documents'))->toThrow(StorageException::class);

    unlink($file);
});

test('an interrupted write never leaves a record that can be read but is not listed', function () use ($fresh) {
    $directory = $fresh();
    $failing = new class($directory) extends FilesystemRecordStore {
        public int $failAt = 0;

        private int $writes = 0;

        protected function replace(string $file, string $contents): void
        {
            if (++$this->writes === $this->failAt)
            {
                throw new ErrorException('the disk is full');
            }

            parent::replace($file, $contents);
        }
    };
    $failing->put('documents', 'a', 'one');
    $failing->failAt = 4;

    expect(fn () => $failing->put('documents', 'b', 'two'))->toThrow(StorageException::class);

    $store = new FilesystemRecordStore($directory);

    expect($store->get('documents', 'b'))->toBeNull()
        ->and(in_array('a', $store->keys('documents'), true))->toBeTrue()
        ->and($store->get('documents', 'a'))->toBe('one');
});

test('a damaged index stops a write before anything changes', function () use ($fresh) {
    $directory = $fresh();
    $store = new FilesystemRecordStore($directory);
    $store->put('documents', 'a', 'one');
    file_put_contents($directory . '/documents/index', "not base64 !!\n");

    expect(fn () => $store->put('documents', 'b', 'two'))->toThrow(StorageException::class)
        ->and($store->get('documents', 'b'))->toBeNull();
});

test('a deleted record is gone even if the index could not be updated', function () use ($fresh) {
    $directory = $fresh();
    $failing = new class($directory) extends FilesystemRecordStore {
        public bool $fail = false;

        protected function replace(string $file, string $contents): void
        {
            if ($this->fail)
            {
                throw new ErrorException('the disk is full');
            }

            parent::replace($file, $contents);
        }
    };
    $failing->put('documents', 'a', 'one');
    $failing->fail = true;

    expect(fn () => $failing->delete('documents', 'a'))->toThrow(StorageException::class)
        ->and((new FilesystemRecordStore($directory))->get('documents', 'a'))->toBeNull();
});

test('a failed write leaves no temporary file behind, and blank or repeated index lines are ignored', function () use (
    $fresh,
    $files,
) {
    $directory = $fresh();
    $store = new FilesystemRecordStore($directory);
    $store->put('documents', 'a', 'one');
    mkdir($directory . '/documents/' . hash('sha256', 'b') . '.record');

    expect(fn () => $store->put('documents', 'b', 'two'))->toThrow(StorageException::class)
        ->and(array_filter($files($directory), fn (string $path) => str_ends_with($path, '.tmp')))->toBe([]);

    $line = ':' . base64_encode('a');
    file_put_contents($directory . '/documents/index', "{$line}\n\n{$line}\n");

    expect($store->keys('documents'))->toBe(['a']);
});
