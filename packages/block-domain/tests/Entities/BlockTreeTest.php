<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\DuplicateBlockIdException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

test('a tree rejects duplicate ids at any depth', function () {
    expect(fn () => new BlockTree(block('a', children: block('b')), block('b')))->toThrow(DuplicateBlockIdException::class);
});

test('a tree finds blocks, their parents, and walks parents first', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b'), block('c', 'acme/box', [], block('d'))), block('e'));
    expect($tree->find('d')->id)->toBe('d')->and($tree->parentOf('d')->id)->toBe('c')
        ->and($tree->parentOf('a'))->toBeNull()->and($tree->find('zz'))->toBeNull()
        ->and(array_map(fn (Block $b) => $b->id, $tree->walk()))->toBe(['a', 'b', 'c', 'd', 'e']);
});

test('replace returns a new tree and leaves the original untouched', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b', 'acme/box', ['n' => 1])));
    $next = $tree->replace('b', $tree->find('b')->withSettings(['n' => 2]));
    expect($next->find('b')->settings)->toBe(['n' => 2])->and($tree->find('b')->settings)->toBe(['n' => 1]);
});

test('ids lists every id depth-first', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b')), block('c'));
    expect($tree->ids())->toBe(['a', 'b', 'c'])->and(array_map(fn (Block $b) => $b->id, $tree->roots()))->toBe(['a', 'c']);
});

test('replace can swap a root and keeps its position', function () {
    $tree = new BlockTree(block('a'), block('b'), block('c'));
    $next = $tree->replace('b', block('b', 'acme/other'));
    expect($next->ids())->toBe(['a', 'b', 'c'])->and($next->find('b')->type->toString())->toBe('acme/other');
});

test('replace with an unknown id yields an equal tree', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b')));
    expect($tree->replace('zz', block('zz'))->ids())->toBe(['a', 'b']);
});

test('replace that introduces a duplicate id throws', function () {
    $tree = new BlockTree(block('a'), block('b'));
    expect(fn () => $tree->replace('b', block('a')))->toThrow(DuplicateBlockIdException::class);
});

test('block withers return new instances and leave the original unchanged', function () {
    $b = block('a', 'acme/box', ['x' => 1]);
    $path = SettingPath::fromString('x');
    $binding = Binding::to('acme/src');
    $child = block('k');
    $next = $b->withId('z')->withTypeVersion(3)->withSettings(['y' => 2])->withExtension('acme/vis', ['on' => true]);
    expect($next->id)->toBe('z')->and($next->typeVersion)->toBe(3)->and($next->settings)->toBe(['y' => 2])
        ->and($next->extensions)->toBe(['acme/vis' => ['on' => true]])
        ->and($b->id)->toBe('a')->and($b->typeVersion)->toBe(1)->and($b->settings)->toBe(['x' => 1])
        ->and($b->extensions)->toBe([]);
    $bound = $b->withBinding($path, $binding);
    expect($bound->bindings)->toBe(['x' => $binding])->and($b->bindings)->toBe([])
        ->and($bound->withoutBinding($path)->bindings)->toBe([])
        ->and($b->withChildren($child)->children)->toBe([$child])->and($b->children)->toBe([]);
});

test('withBinding on an existing path replaces it in place', function () {
    $b = block('a')
        ->withBinding(SettingPath::fromString('one'), Binding::to('s1'))
        ->withBinding(SettingPath::fromString('two'), Binding::to('s2'));
    $next = $b->withBinding(SettingPath::fromString('one'), Binding::to('s3'));
    expect(array_keys($next->bindings))->toBe(['one', 'two'])->and($next->bindings['one']->source)->toBe('s3');
});

test('a block needs a non-empty id and Block children', function () {
    expect(fn () => block(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Block('a', BlockName::fromString('acme/box'), 1, [], [], [], ['nope']))
        ->toThrow(InvalidArgumentException::class);
});

test('a document can swap its tree', function () {
    $doc = new Document('d1', 'page', new BlockTree(block('a')));
    $next = $doc->withTree(new BlockTree(block('b')));
    expect($next->tree->ids())->toBe(['b'])->and($doc->tree->ids())->toBe(['a'])
        ->and($next->id)->toBe('d1')->and($next->kind)->toBe('page')->and($next->formatVersion)->toBe(1);
});
