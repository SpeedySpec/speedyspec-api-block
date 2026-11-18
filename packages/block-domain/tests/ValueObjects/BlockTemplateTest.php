<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTemplateException;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

test('a template needs a valid name, a title and at least one block', function (
    string $name,
    string $title,
    BlockTree $tree,
) {
    expect(fn () => new BlockTemplate($name, $title, $tree))->toThrow(InvalidBlockTemplateException::class);
})->with([
    'bad name' => ['Not A Name', 'T', new BlockTree(block('a'))],
    'trailing newline' => ["acme/hero\n", 'T', new BlockTree(block('a'))],
    'empty title' => ['acme/hero', '', new BlockTree(block('a'))],
    'blank title' => ['acme/hero', '   ', new BlockTree(block('a'))],
    'no blocks' => ['acme/hero', 'Hero', new BlockTree()],
]);

test('a template keeps its tree and details', function () {
    $tree = new BlockTree(block('a'), block('b'));
    $template = new BlockTemplate('acme/hero', 'Hero', $tree, 'A hero section.', 'sections');

    expect($template->name)->toBe('acme/hero')
        ->and($template->title)->toBe('Hero')
        ->and($template->tree->ids())->toBe(['a', 'b'])
        ->and($template->description)->toBe('A hero section.')
        ->and($template->category)->toBe('sections');
});
