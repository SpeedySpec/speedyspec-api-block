<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\CatalogueEntry;
use SpeedySpec\Block\Domain\ValueObjects\Position;

if (!function_exists('entryNames'))
{
    function entryNames(array $entries): array
    {
        return array_map(fn (CatalogueEntry $e) => $e->type->name->toString(), $entries);
    }
}

test('lists every block type with what a builder shows, in registration order', function () {
    $settings = ['type' => 'object', 'properties' => [
        'text' => ['type' => 'string', 'title' => 'Text', 'x-control' => 'textarea'],
    ]];
    [$catalogue] = catalogue(function ($c) use ($settings) {
        $c->get(BlockPackageLoaderInterface::class)->load(package('acme/basic', function ($r) use ($settings) {
            $r->type(type('acme/note', [
                'title' => 'Note', 'description' => 'A note.', 'category' => 'text', 'keywords' => ['memo'],
                'icon' => 'note', 'children' => true, 'examples' => [[]], 'settings' => $settings,
            ]));
        }));
        types($c)->add(type('host/plain'));
    });

    expect(entryNames($catalogue->all()))->toBe(['acme/note', 'host/plain'])
        ->and($catalogue->find(BlockName::fromString('acme/note'))->toArray())->toBe([
            'name' => 'acme/note',
            'title' => 'Note',
            'description' => 'A note.',
            'category' => 'text',
            'keywords' => ['memo'],
            'icon' => 'note',
            'package' => 'acme/basic',
            'packageTitle' => 'Test blocks',
            'acceptsChildren' => true,
            'settings' => $settings,
            'examples' => [[]],
            'transforms' => [],
        ])
        ->and($catalogue->find(BlockName::fromString('host/plain'))->package)->toBeNull()
        ->and($catalogue->find(BlockName::fromString('host/plain'))->toArray()['acceptsChildren'])->toBeFalse()
        ->and($catalogue->find(BlockName::fromString('gone/away')))->toBeNull();
});

test('narrows the list to what may be placed at a position', function () {
    [$catalogue] = catalogue(function ($c) {
        types($c)->add(type('acme/form', ['children' => ['acme/field']]));
        types($c)->add(type('acme/field', ['parents' => ['acme/form']]));
        types($c)->add(type('acme/text'));
    });
    $document = doc(block('f', 'acme/form'), block('t', 'acme/text'), block('u', 'gone/away'));

    expect(entryNames($catalogue->placeableAt($document, Position::inside('f', 0))))->toBe(['acme/field'])
        ->and(entryNames($catalogue->placeableAt($document, Position::root(0))))->toBe(['acme/form', 'acme/text'])
        ->and(entryNames($catalogue->placeableAt($document, Position::inside('t', 0))))->toBe([])
        ->and(entryNames($catalogue->placeableAt($document, Position::inside('u', 0))))->toBe([])
        ->and(entryNames($catalogue->placeableAt($document, Position::inside('nope', 0))))->toBe([]);
});

test('the catalogue reflects a block type registered after it was built', function () {
    [$catalogue, $c] = catalogue();
    types($c)->add(type('late/arrival'));

    expect(entryNames($catalogue->all()))->toBe(['late/arrival']);
});

test('a catalogue entry names the package that defined it, by title', function () {
    [$catalogue] = catalogue(function ($c) {
        $c->get(BlockPackageLoaderInterface::class)
            ->load(package('acme/basic', fn ($r) => $r->type(type('acme/box')), 'Acme blocks'));
        types($c)->add(type('host/added'));
    });

    expect($catalogue->find(BlockName::fromString('acme/box'))->toArray()['packageTitle'])->toBe('Acme blocks')
        ->and($catalogue->find(BlockName::fromString('host/added'))->toArray()['packageTitle'])->toBe('');
});
