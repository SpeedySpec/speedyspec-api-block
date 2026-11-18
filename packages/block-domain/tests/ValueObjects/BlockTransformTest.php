<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTransform;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\CatalogueMetadata;

test('a transform maps each setting of the type it turns into to a setting of its own', function () {
    $transform = new BlockTransform(BlockName::fromString('core/heading'), ['text' => 'text', 'title' => 'caption']);

    expect($transform->to->toString())->toBe('core/heading')
        ->and($transform->settings)->toBe(['text' => 'text', 'title' => 'caption'])
        ->and(BlockTransform::fromArray($transform->toArray())->toArray())->toBe([
            'to' => 'core/heading',
            'settings' => ['text' => 'text', 'title' => 'caption'],
        ]);
});

test('a transform refuses a map that is not setting names to setting names', function (array $settings) {
    expect(fn () => new BlockTransform(BlockName::fromString('core/heading'), $settings))
        ->toThrow(InvalidBlockTypeException::class);
})->with([
    'empty target' => [['' => 'text']],
    'source not text' => [['text' => 3]],
    'empty source' => [['text' => '']],
    'a list' => [['text']],
]);

test('a transform read from data needs a type to turn into and a map of settings', function (array $data) {
    expect(fn () => BlockTransform::fromArray($data))->toThrow(InvalidBlockTypeException::class);
})->with([
    [['settings' => []]],
    [['to' => 'Not A Name', 'settings' => []]],
    [['to' => 'core/heading', 'settings' => 'text']],
]);

$paragraph = fn (array $transforms) => new BlockType(
    BlockName::fromString('core/paragraph'),
    new CatalogueMetadata('Paragraph'),
    1,
    ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]],
    transforms: $transforms,
);

test('a block type declares the types it can turn into, and finds each by name', function () use ($paragraph) {
    $heading = new BlockTransform(BlockName::fromString('core/heading'), ['text' => 'text']);
    $type = $paragraph([$heading]);

    expect($type->transformTo(BlockName::fromString('core/heading')))->toBe($heading)
        ->and($type->transformTo(BlockName::fromString('core/quote')))->toBeNull()
        ->and($type->toArray()['transforms'])->toBe([['to' => 'core/heading', 'settings' => ['text' => 'text']]])
        ->and(BlockType::fromArray($type->toArray())->transforms[0]->toArray())->toBe($heading->toArray());
});

test('a block type refuses transforms that could not be carried out', function (array $transforms) use ($paragraph) {
    expect(fn () => $paragraph($transforms))->toThrow(InvalidBlockTypeException::class);
})->with([
    'not a transform' => [fn () => [['to' => 'core/heading', 'settings' => []]]],
    'two to one type' => [fn () => [
        new BlockTransform(BlockName::fromString('core/heading'), ['text' => 'text']),
        new BlockTransform(BlockName::fromString('core/heading'), []),
    ]],
    'to itself' => [fn () => [new BlockTransform(BlockName::fromString('core/paragraph'), ['text' => 'text'])]],
    'unknown source setting' => [fn () => [new BlockTransform(BlockName::fromString('core/heading'), ['text' => 'body'])]],
]);

test('transforms read from data must be a list of arrays', function () {
    expect(fn () => BlockType::fromArray(['name' => 'acme/x', 'title' => 'X', 'transforms' => 'core/y']))
        ->toThrow(InvalidBlockTypeException::class)
        ->and(fn () => BlockType::fromArray(['name' => 'acme/x', 'title' => 'X', 'transforms' => ['core/y']]))
        ->toThrow(InvalidBlockTypeException::class);
});
