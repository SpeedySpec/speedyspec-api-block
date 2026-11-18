<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusalKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;

$setup = function ($c) {
    types($c)->add(type('acme/form', ['children' => ['acme/field'], 'settings' => [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'maxLength' => 10],
            'to' => ['type' => 'string', 'x-private' => true],
        ],
    ]]));
    types($c)->add(type('acme/field', ['parents' => ['acme/form'], 'version' => 2, 'settings' => [
        'type' => 'object',
        'required' => ['key'],
        'properties' => ['key' => ['type' => 'string'], 'label' => ['type' => 'string', 'default' => 'Field']],
    ]]));
    types($c)->add(type('acme/box', ['children' => true]));
    sources($c)->add(new ArrayDataSource('acme/values', ['k' => 'v'], ['type' => 'string']));
    $c->get(DecoratorRegistryInterface::class)->add(new Decorator(
        'acme/vis',
        ['type' => 'object', 'properties' => ['hide' => ['type' => 'boolean']]],
        ['acme/box'],
    ));
};

test('insert adds a block with a new id at the position and leaves the input alone', function () use ($setup) {
    [$editor] = editor($setup);
    $document = doc(block('f', 'acme/form', [], block('n', 'acme/field', ['key' => 'name'])));

    $result = $editor->insert($document, BlockName::fromString('acme/field'), Position::inside('f', 0), [
        'key' => 'email',
    ]);
    $new = $result->affectedIds[0];

    expect($result->isAccepted())->toBeTrue()
        ->and($result->document->tree->find('f')->children[0]->id)->toBe($new)
        ->and($new)->not->toBe('n')
        ->and($result->document->tree->find($new)->settings)->toBe(['key' => 'email'])
        ->and($result->document->tree->find($new)->typeVersion)->toBe(2)
        ->and($document->tree->ids())->toBe(['f', 'n']);
});

test('insert refuses what would break the document, with every reason', function () use ($setup) {
    [$editor] = editor($setup);
    $document = doc(block('f', 'acme/form'), block('b', 'acme/box'));
    $field = BlockName::fromString('acme/field');
    $try = fn (BlockName $type, Position $at, array $settings = []) => refusals(
        $editor->insert($document, $type, $at, $settings)
    );

    expect($try($field, Position::root(0), ['key' => 'k']))->toBe([EditRefusalKind::DisallowedPlacement])
        ->and($try($field, Position::inside('b', 0), ['key' => 'k']))->toBe([EditRefusalKind::DisallowedPlacement])
        ->and($try($field, Position::inside('f', 0)))->toBe([EditRefusalKind::InvalidSettings])
        ->and($try($field, Position::inside('f', 3), ['key' => 'k']))->toBe([EditRefusalKind::InvalidPosition])
        ->and($try($field, Position::inside('nope', 0), ['key' => 'k']))->toBe([EditRefusalKind::InvalidPosition])
        ->and($try(BlockName::fromString('gone/away'), Position::root(0)))->toBe([EditRefusalKind::UnknownType])
        ->and($try($field, Position::inside('b', 0)))->toBe([
            EditRefusalKind::DisallowedPlacement,
            EditRefusalKind::InvalidSettings,
        ])
        ->and($editor->insert($document, $field, Position::root(0))->document)->toBeNull();
});

test('pasted blocks always get new ids and are validated as a whole', function () use ($setup) {
    [$editor] = editor($setup);
    $document = doc(block('f', 'acme/form', [], block('n', 'acme/field', ['key' => 'name'])));
    $pasted = [block('f', 'acme/form', [], block('n', 'acme/field', ['key' => 'again'])), block('b', 'acme/box')];

    $result = $editor->insertBlocks($document, $pasted, Position::root(1));

    expect($result->isAccepted())->toBeTrue()
        ->and(count($result->affectedIds))->toBe(3)
        ->and(array_intersect($result->affectedIds, ['f', 'n', 'b']))->toBe([])
        ->and(count(array_unique($result->document->tree->ids())))->toBe(5)
        ->and($result->document->tree->roots()[0]->id)->toBe('f')
        ->and($result->document->tree->roots()[1]->id)->toBe($result->affectedIds[0])
        ->and($result->document->tree->roots()[2]->id)->toBe($result->affectedIds[2])
        ->and($pasted[0]->id)->toBe('f');

    $bad = [block('x', 'acme/box', [], block('y', 'acme/field', []))];

    expect(refusals($editor->insertBlocks($document, $bad, Position::root(0))))->toBe([
        EditRefusalKind::DisallowedPlacement,
        EditRefusalKind::InvalidSettings,
    ]);
});

test('a template can be inserted twice without id clashes and stays unchanged', function () use ($setup) {
    [$editor] = editor($setup);
    $tree = new BlockTree(block('t', 'acme/form', [], block('u', 'acme/field', ['key' => 'email'])));
    $template = new BlockTemplate('acme/contact', 'Contact', $tree);

    $once = $editor->insertTemplate(doc(), $template, Position::root(0));
    $twice = $editor->insertTemplate($once->document, $template, Position::root(1));

    expect($twice->isAccepted())->toBeTrue()
        ->and(count(array_unique($twice->document->tree->ids())))->toBe(4)
        ->and($template->tree->ids())->toBe(['t', 'u']);
});

test('move keeps ids, checks the new parent, and cannot go inside itself', function () use ($setup) {
    [$editor] = editor($setup);
    $document = doc(
        block('a', 'acme/box', [], block('b', 'acme/box', [], block('c', 'acme/box'))),
        block('f', 'acme/form'),
    );

    $moved = $editor->move($document, 'c', Position::root(0));

    expect($moved->document->tree->ids())->toBe(['c', 'a', 'b', 'f'])
        ->and(refusals($editor->move($document, 'a', Position::inside('c', 0))))->toBe([EditRefusalKind::MoveIntoSelf])
        ->and(refusals($editor->move($document, 'a', Position::inside('a', 0))))->toBe([EditRefusalKind::MoveIntoSelf])
        ->and(refusals($editor->move($document, 'c', Position::inside('f', 0))))
        ->toBe([EditRefusalKind::DisallowedPlacement])
        ->and(refusals($editor->move($document, 'zz', Position::root(0))))->toBe([EditRefusalKind::UnknownBlock])
        ->and($editor->move($document, 'f', Position::root(1))->document->tree->ids())->toBe(['a', 'b', 'c', 'f']);
});

test('remove and duplicate', function () use ($setup) {
    [$editor] = editor($setup);
    $document = doc(block('a', 'acme/box', [], block('b', 'acme/box')), block('z', 'acme/box'));

    $copy = $editor->duplicate($document, 'a');

    expect($editor->remove($document, 'a')->document->tree->ids())->toBe(['z'])
        ->and(refusals($editor->remove($document, 'nope')))->toBe([EditRefusalKind::UnknownBlock])
        ->and(count($copy->affectedIds))->toBe(2)
        ->and($copy->document->tree->roots()[1]->id)->toBe($copy->affectedIds[0])
        ->and($copy->document->tree->roots()[2]->id)->toBe('z')
        ->and(count(array_unique($copy->document->tree->ids())))->toBe(5);
});

test('updateSettings replaces settings, validates them and updates the type version', function () use ($setup) {
    [$editor] = editor($setup);
    $old = block('n', 'acme/field', ['key' => 'name'])
        ->withBinding(SettingPath::fromString('label'), Binding::to('acme/values', ['key' => 'k']));
    $document = doc(block('f', 'acme/form', ['title' => 'Hi'], $old));

    $ok = $editor->updateSettings($document, 'n', ['key' => 'email']);
    $bad = $editor->updateSettings($document, 'f', ['title' => 'far too long a title']);

    expect($ok->document->tree->find('n')->settings)->toBe(['key' => 'email'])
        ->and($ok->document->tree->find('n')->typeVersion)->toBe(2)
        ->and(array_keys($ok->document->tree->find('n')->bindings))->toBe(['label'])
        ->and($ok->affectedIds)->toBe(['n'])
        ->and(refusals($bad))->toBe([EditRefusalKind::InvalidSettings])
        ->and($bad->refusals[0]->path)->toBe('title')
        ->and($bad->refusals[0]->blockId)->toBe('f')
        ->and(refusals($editor->updateSettings($document, 'n', [])))->toBe([EditRefusalKind::InvalidSettings]);
});

test('updateExtension needs a decorator that applies and settings that fit it', function () use ($setup) {
    [$editor] = editor($setup);
    $document = doc(block('b', 'acme/box'), block('f', 'acme/form'));

    $ok = $editor->updateExtension($document, 'b', 'acme/vis', ['hide' => true]);

    expect($ok->document->tree->find('b')->extensions)->toBe(['acme/vis' => ['hide' => true]])
        ->and(refusals($editor->updateExtension($document, 'f', 'acme/vis', ['hide' => true])))
        ->toBe([EditRefusalKind::UnknownDecorator])
        ->and(refusals($editor->updateExtension($document, 'b', 'acme/nope', [])))
        ->toBe([EditRefusalKind::UnknownDecorator])
        ->and($editor->updateExtension($document, 'b', 'acme/vis', ['hide' => 'yes'])->refusals[0]->path)
        ->toBe('@acme/vis:hide');
});

test('setBinding refuses private settings, unknown sources and whatever does not fit', function () use ($setup) {
    [$editor] = editor($setup);
    $document = doc(block('f', 'acme/form'));
    $values = Binding::to('acme/values', ['key' => 'k']);
    $try = fn (string $path, Binding $binding) => refusals(
        $editor->setBinding($document, 'f', SettingPath::fromString($path), $binding)
    );

    $ok = $editor->setBinding($document, 'f', SettingPath::fromString('title'), $values->withFallback('Hello'));

    expect($ok->isAccepted())->toBeTrue()
        ->and(array_keys($ok->document->tree->find('f')->bindings))->toBe(['title'])
        ->and($try('to', $values))->toBe([EditRefusalKind::PrivateBinding])
        ->and($try('title', Binding::to('nope/source')))->toBe([EditRefusalKind::UnknownDataSource])
        ->and($try('title', Binding::to('acme/values', ['key' => 5])))->toBe([EditRefusalKind::InvalidBinding])
        ->and($try('title', $values->withFallback('a fallback that is too long')))
        ->toBe([EditRefusalKind::InvalidBinding])
        ->and($try('missing', $values))->toBe([EditRefusalKind::InvalidBinding])
        ->and(refusals($editor->setBinding($document, 'zz', SettingPath::fromString('title'), $values)))
        ->toBe([EditRefusalKind::UnknownBlock])
        ->and($editor->clearBinding($ok->document, 'f', SettingPath::fromString('title'))
            ->document->tree->find('f')->bindings)->toBe([])
        ->and($editor->clearBinding($document, 'f', SettingPath::fromString('title'))->isAccepted())->toBeTrue();
});

test('a document the editor accepts resolves without placement or settings issues', function () use ($setup) {
    [$editor, $c] = editor($setup);

    $result = $editor->insert(doc(), BlockName::fromString('acme/form'), Position::root(0), ['title' => 'Hi']);
    $result = $editor->insert(
        $result->document,
        BlockName::fromString('acme/field'),
        Position::inside($result->affectedIds[0], 0),
        ['key' => 'email'],
    );

    expect($c->get(DocumentResolverInterface::class)->resolve($result->document, Mode::Front)->hasIssues())
        ->toBeFalse();
});
