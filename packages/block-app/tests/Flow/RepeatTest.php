<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Flow\FlowPackage;
use SpeedySpec\Block\App\Registries\CollectionSourceRegistry;
use SpeedySpec\Block\App\Registries\ConditionRegistry;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

$people = [
    ['name' => 'Ann', 'address' => ['city' => 'Oslo'], 'pets' => [['name' => 'Rex'], ['name' => 'Tom']]],
    ['name' => 'Bob', 'address' => ['city' => 'Rome'], 'pets' => []],
    ['name' => 'Cy', 'pets' => [['name' => 'Kit']]],
];
$setup = function ($c) use ($people) {
    $sources = $c->get(CollectionSourceRegistryInterface::class);
    $sources->add(collection('acme/people', fn () => $people));
    $sources->add(collection('acme/none', fn () => []));
    $sources->add(collection('acme/boom', fn () => throw new RuntimeException('no')));
    $sources->add(collection(
        'acme/pets',
        fn (array $arguments, $context) => $context->currentItem()?->item['pets'] ?? [],
    ));
    $sources->add(collection(
        'acme/numbers',
        fn (array $arguments) => range(1, $arguments['count']),
        ['type' => 'object', 'required' => ['count'], 'properties' => ['count' => ['type' => 'integer']]],
    ));
};
$field = fn (string $id, string $path, string $stored = 'none') => block($id, 'acme/text', ['text' => $stored])
    ->withBinding(SettingPath::fromString('text'), Binding::to('core/current-item', ['field' => $path]));
$repeat = fn (string $id, array $settings, Block ...$children) => block($id, 'core/repeat', $settings, ...$children);
$front = fn ($renderer, Block ...$blocks) => $renderer->render(doc(...$blocks), new RenderRequest());

describe('FlowPackage', function () {
    $validator = new SubsetSchemaValidator();

    blockPackageContract(
        fn () => new FlowPackage(
            new ConditionRegistry($validator),
            new CollectionSourceRegistry($validator),
            $validator,
        ),
        __DIR__ . '/../../composer.json',
    );
});

test('children are drawn once per item, each bound to that item', function () use ($setup, $field, $repeat, $front) {
    [$renderer] = flow($setup);

    $result = $front($renderer, $repeat('r', ['source' => 'acme/people'], $field('t', 'name')));

    expect($result->output)->toBe('<p>Ann</p><p>Bob</p><p>Cy</p>')->and($result->issues)->toBe([]);
});

test('a field path reaches into an item, and a missing field leaves the stored value', function () use (
    $setup,
    $field,
    $repeat,
    $front,
) {
    [$renderer] = flow($setup);

    $result = $front($renderer, $repeat('r', ['source' => 'acme/people'], $field('t', 'address.city', 'nowhere')));

    expect($result->output)->toBe('<p>Oslo</p><p>Rome</p><p>nowhere</p>');
});

test('the limit caps the items and arguments reach the source', function () use ($setup, $field, $repeat, $front) {
    [$renderer] = flow($setup);
    $whole = block('t', 'acme/text')
        ->withBinding(SettingPath::fromString('text'), Binding::to('core/current-item')->withFallback('x'));

    $limited = $front($renderer, $repeat('r', ['source' => 'acme/people', 'limit' => 2], $field('t', 'name')));
    $numbers = $front($renderer, $repeat('r', ['source' => 'acme/numbers', 'arguments' => ['count' => 3]], $whole));

    expect($limited->output)->toBe('<p>Ann</p><p>Bob</p>')
        ->and($numbers->output)->toBe('<p>x</p><p>x</p><p>x</p>');
});

test('a repeat inside a repeat works on its own items', function () use ($setup, $field, $repeat, $front) {
    [$renderer] = flow($setup);
    $inner = $repeat('pets', ['source' => 'acme/pets'], $field('pet', 'name'));

    $result = $front($renderer, $repeat('r', ['source' => 'acme/people'], $field('t', 'name'), $inner));

    expect($result->output)->toBe('<p>Ann</p><p>Rex</p><p>Tom</p><p>Bob</p><p>Cy</p><p>Kit</p>');
});

test('a repeat that cannot get its items draws nothing and says why, without stopping the page', function () use (
    $setup,
    $field,
    $repeat,
    $front,
) {
    [$renderer] = flow($setup);
    $after = block('z', 'acme/text', ['text' => 'after']);
    $try = fn (array $settings) => $front($renderer, $repeat('r', $settings, $field('t', 'name')), $after);

    expect($try(['source' => 'acme/gone'])->output)->toBe('<p>after</p>')
        ->and(idsAndKinds($try(['source' => 'acme/gone'])->issues))->toBe([['r', IssueKind::UnknownCollection]])
        ->and(idsAndKinds($try(['source' => 'acme/boom'])->issues))->toBe([['r', IssueKind::InvalidView]])
        ->and(idsAndKinds($try(['source' => 'acme/numbers', 'arguments' => ['count' => 'x']])->issues))
        ->toBe([['r', IssueKind::InvalidCollection]])
        ->and($try(['source' => 'acme/none'])->output)->toBe('<p>after</p>')
        ->and($try(['source' => 'acme/none'])->issues)->toBe([])
        ->and($try([])->output)->toBe('<p>after</p>')
        ->and($try([])->issues)->toBe([]);
});

test('an editor always has the repeated blocks to work on and the collections to choose from', function () use (
    $setup,
    $field,
    $repeat,
) {
    [$renderer, $c] = flow($setup);
    $admin = fn (array $settings) => $renderer->render(
        doc($repeat('r', $settings, $field('t', 'name', 'placeholder'))),
        new RenderRequest(Mode::Admin),
    );
    $resolved = $c->get(DocumentResolverInterface::class)
        ->resolve(doc($repeat('r', [], $field('t', 'name'))), Mode::Admin);

    expect($admin([])->output)->toBe('<p>placeholder</p>')
        ->and($admin(['source' => 'acme/none'])->output)->toBe('<p>placeholder</p>')
        ->and($admin(['source' => 'acme/people', 'limit' => 2])->output)->toBe('<p>Ann</p><p>Bob</p>')
        ->and($resolved->find('r')->admin->values['collections'])
        ->toBe(['acme/people', 'acme/none', 'acme/boom', 'acme/pets', 'acme/numbers']);
});

test('a collection is never asked for items with options it would refuse', function () use ($field, $repeat, $front) {
    $calls = 0;
    [$renderer] = flow(function ($c) use (&$calls) {
        $c->get(CollectionSourceRegistryInterface::class)->add(collection(
            'acme/strict',
            function () use (&$calls) {
                $calls++;

                return [['name' => 'Ann']];
            },
            ['type' => 'object', 'required' => ['kind'], 'properties' => ['kind' => ['type' => 'string']]],
        ));
    });
    $draw = fn (array $arguments, Mode $mode) => $renderer->render(
        doc($repeat('r', ['source' => 'acme/strict', 'arguments' => $arguments], $field('t', 'name'))),
        new RenderRequest($mode),
    )->output;

    expect($draw(['kind' => 5], Mode::Front))->toBe('')
        ->and($draw([], Mode::Admin))->not->toContain('Ann')
        ->and($calls)->toBe(0)
        ->and($draw(['kind' => 'people'], Mode::Front))->toBe('<p>Ann</p>')
        ->and($calls)->toBe(1);
});

test('the current item is nothing outside a repeat', function () use ($setup, $field, $front) {
    [$renderer] = flow($setup);

    expect($front($renderer, $field('t', 'name', 'stored'))->output)->toBe('<p>stored</p>');
});

test('a block inside a repeat can be shown only for items with a given field value', function () use (
    $setup,
    $field,
    $repeat,
    $front,
) {
    [$renderer] = flow($setup);
    $rule = fn (array $arguments, bool $negate = false) => [[
        ['condition' => 'core/current-item-is', 'arguments' => $arguments, 'negate' => $negate],
    ]];
    $only = fn (array $groups) => $field('t', 'name')->withExtension('core/visibility', ['groups' => $groups]);
    $draw = fn (array $groups) => $front($renderer, $repeat('r', ['source' => 'acme/people'], $only($groups)))->output;

    expect($draw($rule(['field' => 'address.city', 'value' => 'Rome'])))->toBe('<p>Bob</p>')
        ->and($draw($rule(['field' => 'address.city', 'value' => 'Rome'], true)))->toBe('<p>Ann</p><p>Cy</p>')
        ->and($draw($rule(['field' => 'name', 'value' => 5])))->toBe('')
        ->and($front($renderer, $only($rule(['field' => 'name', 'value' => 'Ann'])))->output)->toBe('');
});

test('the collection registry lists, removes and refuses an unsupported schema', function () use ($setup) {
    [, $c] = flow($setup);
    $sources = $c->get(CollectionSourceRegistryInterface::class);

    $sources->remove('acme/boom');

    expect($sources->get('acme/boom'))->toBeNull()
        ->and($sources->get('acme/people')->name())->toBe('acme/people')
        ->and(count($sources->all()))->toBe(4)
        ->and(fn () => $sources->add(collection('acme/bad', fn () => [], ['oneOf' => []])))
        ->toThrow(\SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException::class);
});
