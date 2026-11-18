<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\ConditionRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusalKind;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

$setup = function ($c) {
    $conditions = $c->get(ConditionRegistryInterface::class);
    $conditions->add(condition('acme/yes', fn () => true));
    $conditions->add(condition('acme/no', fn () => false));
    $conditions->add(condition('acme/boom', fn () => throw new RuntimeException('no')));
    $conditions->add(condition(
        'acme/is',
        fn (array $arguments) => $arguments['value'] === 'go',
        ['type' => 'object', 'required' => ['value'], 'properties' => ['value' => ['type' => 'string']]],
    ));
};
$rule = fn (string $name, array $arguments = [], bool $negate = false) => [
    'condition' => $name,
    'arguments' => $arguments,
    'negate' => $negate,
];
$text = fn (array ...$groups) => block('a', 'acme/text', ['text' => 'hi'])
    ->withExtension('core/visibility', ['groups' => $groups]);
$front = fn ($renderer, $block) => $renderer->render(doc($block), new RenderRequest())->output;

test('a block with no visibility rules, or an empty list of groups, is shown', function () use ($setup, $text, $front) {
    [$renderer] = flow($setup);

    expect($front($renderer, block('a', 'acme/text', ['text' => 'hi'])))->toBe('<p>hi</p>')
        ->and($front($renderer, $text()))->toBe('<p>hi</p>');
});

test('a block is shown when every condition of any one group holds', function () use ($setup, $rule, $text, $front) {
    [$renderer] = flow($setup);
    $yes = $rule('acme/yes');
    $no = $rule('acme/no');

    expect($front($renderer, $text([$yes])))->toBe('<p>hi</p>')
        ->and($front($renderer, $text([$no])))->toBe('')
        ->and($front($renderer, $text([$yes, $no])))->toBe('')
        ->and($front($renderer, $text([$no], [$yes])))->toBe('<p>hi</p>')
        ->and($front($renderer, $text([$no], [$yes, $no])))->toBe('');
});

test('a rule can be negated and receives its arguments', function () use ($setup, $rule, $text, $front) {
    [$renderer] = flow($setup);

    expect($front($renderer, $text([$rule('acme/no', [], true)])))->toBe('<p>hi</p>')
        ->and($front($renderer, $text([$rule('acme/yes', [], true)])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/is', ['value' => 'go'])])))->toBe('<p>hi</p>')
        ->and($front($renderer, $text([$rule('acme/is', ['value' => 'stop'])])))->toBe('');
});

test('a rule that cannot be judged hides the block from visitors, even when negated', function () use (
    $setup,
    $rule,
    $text,
    $front,
) {
    [$renderer] = flow($setup);

    expect($front($renderer, $text([$rule('acme/gone')])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/gone', [], true)])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/is', ['value' => 5])])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/is', [], true)])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/boom')])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/boom', [], true)])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/gone')], [$rule('acme/yes')])))->toBe('<p>hi</p>');
});

test('an editor sees a hidden block marked as hidden, and a broken rule as an issue on the block', function () use (
    $setup,
    $rule,
    $text,
) {
    [, $c] = flow($setup);
    $resolver = $c->get(DocumentResolverInterface::class);

    $hidden = $resolver->resolve(doc($text([$rule('acme/no')])), Mode::Admin);
    $broken = $resolver->resolve(
        doc($text([$rule('acme/yes'), $rule('acme/gone')], [$rule('acme/is', ['value' => 5])])),
        Mode::Admin,
    );

    expect($hidden->find('a')->isResolved())->toBeTrue()
        ->and($hidden->find('a')->admin->hiddenOnFront)->toBeTrue()
        ->and($hidden->issues)->toBe([])
        ->and(kinds($broken->issues))->toBe([IssueKind::UnknownCondition, IssueKind::InvalidCondition])
        ->and($broken->issues[0]->path)->toBe('@core/visibility:groups.0.1')
        ->and($broken->issues[1]->path)->toBe('@core/visibility:groups.1.0');
});

test('a hidden block never asks its data sources for values', function () use ($setup, $rule, $text, $front) {
    $calls = 0;
    [$renderer] = flow(function ($c) use ($setup, &$calls) {
        $setup($c);
        sources($c)->add(countingSource('acme/count', $calls));
    });
    $bound = $text([$rule('acme/no')])->withBinding(SettingPath::fromString('text'), Binding::to('acme/count'));

    expect($front($renderer, $bound))->toBe('')->and($calls)->toBe(0);
});

test('visibility settings that are not groups of rules are refused when edited', function () use ($setup, $rule) {
    [, $c] = flow($setup);
    $editor = $c->get(DocumentEditorInterface::class);
    $document = doc(block('a', 'acme/text'));

    $ok = $editor->updateExtension($document, 'a', 'core/visibility', ['groups' => [[$rule('acme/yes')]]]);

    expect($ok->isAccepted())->toBeTrue()
        ->and(refusals($editor->updateExtension($document, 'a', 'core/visibility', ['groups' => 'always'])))
        ->toBe([EditRefusalKind::InvalidSettings])
        ->and(refusals($editor->updateExtension($document, 'a', 'core/visibility', ['groups' => [['always']]])))
        ->toBe([EditRefusalKind::InvalidSettings])
        ->and(refusals($editor->updateExtension($document, 'a', 'core/visibility', ['when' => 'x'])))
        ->toBe([EditRefusalKind::InvalidSettings]);
});

test('the condition registry lists what a builder can offer and refuses a bad schema', function () use ($setup) {
    [, $c] = flow($setup);
    $conditions = $c->get(ConditionRegistryInterface::class);

    $conditions->remove('acme/boom');

    expect(array_map(fn ($condition) => $condition->name(), $conditions->all()))
        ->toBe(['core/current-item-is', 'acme/yes', 'acme/no', 'acme/is'])
        ->and($conditions->get('acme/boom'))->toBeNull()
        ->and(fn () => $conditions->add(condition('acme/bad', fn () => true, ['oneOf' => []])))
        ->toThrow(\SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException::class);
});

test('an empty group never makes a restricted block public', function () use ($setup, $rule, $text, $front) {
    [$renderer, $c] = flow($setup);
    $editor = $c->get(DocumentEditorInterface::class);
    $document = doc(block('a', 'acme/text'));

    expect($front($renderer, $text([])))->toBe('')
        ->and($front($renderer, $text([$rule('acme/no')], [])))->toBe('')
        ->and(refusals($editor->updateExtension($document, 'a', 'core/visibility', ['groups' => [[]]])))
        ->toBe([EditRefusalKind::InvalidSettings]);
});

test('a rule the resolver would report is refused when edited', function () use ($setup, $rule) {
    [, $c] = flow($setup);
    $editor = $c->get(DocumentEditorInterface::class);
    $document = doc(block('a', 'acme/text'));
    $try = fn (array $one) => $editor->updateExtension($document, 'a', 'core/visibility', ['groups' => [[$one]]]);

    expect(refusals($try($rule('acme/gone'))))->toBe([EditRefusalKind::InvalidSettings])
        ->and($try($rule('acme/gone'))->refusals[0]->path)->toBe('@core/visibility:groups.0.0')
        ->and(refusals($try($rule('acme/is', ['value' => 5]))))->toBe([EditRefusalKind::InvalidSettings])
        ->and($try($rule('acme/is', ['value' => 'go']))->isAccepted())->toBeTrue();
});
