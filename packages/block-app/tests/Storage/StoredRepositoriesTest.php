<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Serialization\JsonBlockTemplateSerializer;
use SpeedySpec\Block\App\Serialization\JsonDocumentSerializer;
use SpeedySpec\Block\App\Storage\StorageServiceProvider;
use SpeedySpec\Block\App\Storage\StoredBlockTemplateRepository;
use SpeedySpec\Block\App\Storage\StoredDocumentRepository;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTemplateException;
use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use SpeedySpec\Block\Infra\Memory\InMemoryRecordStore;

use function SpeedySpec\Block\ContractTests\blockTemplateRepositoryContract;
use function SpeedySpec\Block\ContractTests\documentRepositoryContract;

$templates = fn (?RecordStoreInterface $store = null) => new StoredBlockTemplateRepository(
    $store ?? new InMemoryRecordStore(),
    new JsonBlockTemplateSerializer(new JsonDocumentSerializer()),
);
$documents = fn (?RecordStoreInterface $store = null) => new StoredDocumentRepository(
    $store ?? new InMemoryRecordStore(),
    new JsonDocumentSerializer(),
);

describe('StoredDocumentRepository', function () use ($documents) {
    documentRepositoryContract(fn () => $documents());
});

describe('StoredBlockTemplateRepository', function () use ($templates) {
    blockTemplateRepositoryContract(fn () => $templates());
});

test('a stored document comes back whole from a second repository over the same store', function () use ($documents) {
    $store = new InMemoryRecordStore();
    $block = block('a', 'acme/box', ['n' => 1.0, 'list' => [1, 2]], block('b'))
        ->withExtension('acme/vis', ['hide' => true])
        ->withBinding(SettingPath::fromString('title'), Binding::to('acme/values', ['key' => 'k'])->withFallback('x'));
    $documents($store)->save(doc($block));

    expect($documents($store)->find('d'))->toEqual(doc($block));
});

test('documents and templates with the same key do not collide in one store', function () use (
    $documents,
    $templates,
) {
    $store = new InMemoryRecordStore();
    $page = new \SpeedySpec\Block\Domain\Entities\Document('acme/hero', 'page', new BlockTree(block('a')));
    $documents($store)->save($page);
    $templates($store)->save(new BlockTemplate('acme/hero', 'Hero', new BlockTree(block('t'))));
    $templates($store)->remove('acme/hero');

    expect($documents($store)->find('acme/hero'))->not->toBeNull()
        ->and($templates($store)->all())->toBe([]);
});

test('a record that is not a document or template is reported, naming the record', function () use (
    $documents,
    $templates,
) {
    $store = new InMemoryRecordStore();
    $store->put('documents', 'broken', '{not json');
    $store->put('templates', 'acme/broken', '{"name":"acme/broken"}');

    expect(fn () => $documents($store)->find('broken'))->toThrow(StorageException::class, 'broken')
        ->and(fn () => $templates($store)->find('acme/broken'))->toThrow(StorageException::class, 'acme/broken')
        ->and(fn () => $templates($store)->all())->toThrow(StorageException::class, 'acme/broken');
});

test('a template serializes to text and back, and refuses text that is not a template', function () {
    $serializer = new JsonBlockTemplateSerializer(new JsonDocumentSerializer());
    $template = new BlockTemplate(
        'acme/hero',
        'Hero "quoted"',
        new BlockTree(block('a', 'acme/box', ['n' => 1], block('b'))),
        'A hero.',
        'sections',
    );

    expect($serializer->parse($serializer->serialize($template)))->toEqual($template)
        ->and(fn () => $serializer->parse('[]'))->toThrow(InvalidBlockTemplateException::class)
        ->and(fn () => $serializer->parse('nope'))->toThrow(InvalidBlockTemplateException::class)
        ->and(fn () => $serializer->parse('{"name":"Bad Name","title":"x","document":"{}"}'))
        ->toThrow(InvalidBlockTemplateException::class);
});

test('the storage provider binds both repositories over whichever record store is bound', function () {
    $container = new BlockServiceContainer();
    (new AppServiceProvider())->register($container);
    (new StorageServiceProvider())->register($container);
    $store = new InMemoryRecordStore();
    $container->add(RecordStoreInterface::class, fn () => $store);

    $container->get(DocumentRepositoryInterface::class)->save(doc(block('a')));
    $container->get(BlockTemplateRepositoryInterface::class)
        ->save(new BlockTemplate('acme/hero', 'Hero', new BlockTree(block('t'))));

    expect($container->get(DocumentRepositoryInterface::class))->toBeInstanceOf(StoredDocumentRepository::class)
        ->and($container->get(BlockTemplateRepositoryInterface::class))
        ->toBeInstanceOf(StoredBlockTemplateRepository::class)
        ->and($container->get(BlockTemplateSerializerInterface::class))
        ->toBeInstanceOf(JsonBlockTemplateSerializer::class)
        ->and($store->keys('documents'))->toBe(['d'])
        ->and($store->keys('templates'))->toBe(['acme/hero']);
});

test('a document that could never be read back is refused when saved', function (string $id, string $kind) use (
    $documents,
) {
    $store = new InMemoryRecordStore();
    $document = new \SpeedySpec\Block\Domain\Entities\Document($id, $kind, new BlockTree(block('a')));

    expect(fn () => $documents($store)->save($document))
        ->toThrow(\SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException::class)
        ->and($store->keys('documents'))->toBe([]);
})->with([['', 'page'], ['home', '']]);
