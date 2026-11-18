<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\BlockTypeRegistry;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\App\Serialization\ClientExport;
use SpeedySpec\Block\App\Serialization\JsonDocumentSerializer;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

if (!function_exists('exporter'))
{
    /**
     * Builds a client export over a registry holding the given types.
     */
    function exporter(BlockType ...$types): ClientExport
    {
        $validator = new SubsetSchemaValidator();
        $registry = new BlockTypeRegistry($validator);

        foreach ($types as $type)
        {
            $registry->add($type);
        }

        return new ClientExport($registry, $validator);
    }
}

if (!function_exists('privateFormType'))
{
    /**
     * Builds a form type whose `to` setting is private.
     */
    function privateFormType(): BlockType
    {
        return type('acme/form', ['settings' => ['type' => 'object', 'properties' => [
            'to' => ['type' => 'string', 'x-private' => true], 'title' => ['type' => 'string']]]]);
    }
}

test('front export removes private settings and bindings that target them', function () {
    $form = privateFormType();
    $block = block('f', 'acme/form', ['to' => 'owner@example.test', 'title' => 'Contact'])
        ->withBinding(SettingPath::fromString('to'), Binding::to('acme/values', ['key' => 'x']))
        ->withBinding(SettingPath::fromString('title'), Binding::to('acme/values', ['key' => 'y']));
    $out = exporter($form)->export(new Document('d', 'page', new BlockTree($block)), Mode::Front)->tree->find('f');
    expect($out->settings)->toBe(['title' => 'Contact'])->and(array_keys($out->bindings))->toBe(['title']);
});

test('front export strips every setting of an unregistered type but keeps the block and its children', function () {
    $doc = new Document('d', 'page', new BlockTree(block('a', 'gone/away', ['secret' => 's'], block('b', 'acme/box', ['n' => 1]))));
    $out = exporter(type('acme/box'))->export($doc, Mode::Front)->tree;
    expect($out->find('a')->settings)->toBe([])->and($out->find('b')->settings)->toBe(['n' => 1]);
});

test('admin export returns everything', function () {
    $form = privateFormType();
    $doc = new Document('d', 'page', new BlockTree(block('f', 'acme/form', ['to' => 'owner@example.test'])));
    expect(exporter($form)->export($doc, Mode::Admin)->tree->find('f')->settings)->toBe(['to' => 'owner@example.test']);
});

test('admin export returns the same document instance', function () {
    $doc = new Document('d', 'page', new BlockTree(block('f', 'acme/form')));
    expect(exporter(privateFormType())->export($doc, Mode::Admin))->toBe($doc);
});

test('the serialized front export does not contain the private value', function () {
    $form = privateFormType();
    $doc = new Document('d', 'page', new BlockTree(block('f', 'acme/form', ['to' => 'owner@example.test'])));
    $json = (new JsonDocumentSerializer())->serialize(exporter($form)->export($doc, Mode::Front));
    expect($json)->not->toContain('owner@example.test');
});

test('front export of an unregistered type removes all bindings and keeps extensions', function () {
    $b = block('a', 'gone/away', ['x' => 1])
        ->withBinding(SettingPath::fromString('x'), Binding::to('s'))
        ->withBinding(SettingPath::fromString('@acme/vis:hide'), Binding::to('s'))
        ->withExtension('acme/vis', ['hide' => true]);
    $out = exporter()->export(new Document('d', 'page', new BlockTree($b)), Mode::Front)->tree->find('a');

    expect($out->bindings)->toBe([])->and($out->extensions)->toBe(['acme/vis' => ['hide' => true]]);
});

test('front export strips private settings of registered children under an unregistered parent', function () {
    $doc = new Document('d', 'page', new BlockTree(
        block('p', 'gone/away', ['s' => 1], block('f', 'acme/form', ['to' => 'o@example.test', 'title' => 'T']))
    ));
    $out = exporter(privateFormType())->export($doc, Mode::Front)->tree;

    expect($out->find('p')->settings)->toBe([])->and($out->find('f')->settings)->toBe(['title' => 'T']);
});

test('front export keeps a binding that targets a decorator path', function () {
    $b = block('f', 'acme/form', ['to' => 'o@example.test'])
        ->withBinding(SettingPath::fromString('@acme/vis:hide'), Binding::to('s'))
        ->withBinding(SettingPath::fromString('to.sub'), Binding::to('s'));
    $out = exporter(privateFormType())->export(new Document('d', 'page', new BlockTree($b)), Mode::Front)->tree->find('f');

    expect(array_keys($out->bindings))->toBe(['@acme/vis:hide']);
});

test('front export does not change the source document', function () {
    $doc = new Document('d', 'page', new BlockTree(block('f', 'acme/form', ['to' => 'o@example.test'])));
    exporter(privateFormType())->export($doc, Mode::Front);

    expect($doc->tree->find('f')->settings)->toBe(['to' => 'o@example.test']);
});

test('front export keeps the settings of a registered type whose schema has no properties', function () {
    $doc = new Document('d', 'page', new BlockTree(block('a', 'acme/box', ['n' => 1])));

    expect(exporter(type('acme/box'))->export($doc, Mode::Front)->tree->find('a')->settings)->toBe(['n' => 1]);
});

if (!function_exists('formV2'))
{
    /**
     * Builds a form type at version 2 whose private `recipient` setting a migration would have filled from `to`.
     */
    function formV2(): BlockType
    {
        return type('acme/form', ['version' => 2, 'children' => true, 'settings' => ['type' => 'object', 'properties' => [
            'recipient' => ['type' => 'string', 'x-private' => true], 'title' => ['type' => 'string']]]]);
    }
}

test('front export strips every setting and binding of a block stored at an older or newer type version', function (int $version) {
    $b = block('f', 'acme/form', ['to' => 'legacy-secret@example.test', 'title' => 'T'])->withTypeVersion($version)
        ->withBinding(SettingPath::fromString('title'), Binding::to('acme/values', ['key' => 'y']))
        ->withExtension('acme/vis', ['hide' => true]);
    $exported = exporter(formV2())->export(new Document('d', 'page', new BlockTree($b)), Mode::Front);
    $out = $exported->tree->find('f');
    expect($out->settings)->toBe([])->and($out->bindings)->toBe([])
        ->and($out->extensions)->toBe(['acme/vis' => ['hide' => true]])->and($out->typeVersion)->toBe($version)
        ->and((new JsonDocumentSerializer())->serialize($exported))->not->toContain('legacy-secret@example.test');
})->with([1, 3]);

test('front export strips a nested child at an old version under a current parent', function () {
    $child = block('c', 'acme/form', ['to' => 'legacy-secret@example.test']);
    $parent = block('p', 'acme/form', ['recipient' => 'owner@example.test', 'title' => 'P'], $child)->withTypeVersion(2);
    $exported = exporter(formV2())->export(new Document('d', 'page', new BlockTree($parent)), Mode::Front);
    $json = (new JsonDocumentSerializer())->serialize($exported);
    expect($exported->tree->find('p')->settings)->toBe(['title' => 'P'])
        ->and($exported->tree->find('c')->settings)->toBe([])
        ->and($json)->not->toContain('legacy-secret@example.test')
        ->and($json)->not->toContain('owner@example.test');
});
