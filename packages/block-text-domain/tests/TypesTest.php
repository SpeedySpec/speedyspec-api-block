<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusalKind;
use SpeedySpec\Block\Domain\ValueObjects\Position;

$type = fn (string $name) => textContainer()->get(BlockTypeRegistryInterface::class)->get(BlockName::fromString($name));

test('the text blocks have the settings this plan fixes', function () use ($type) {
    $settings = fn (string $name) => array_keys($type($name)->settingsSchema['properties'] ?? []);

    expect($settings('core/heading'))->toBe(['level', 'text', 'anchor'])
        ->and($settings('core/paragraph'))->toBe(['text'])
        ->and($settings('core/list'))->toBe(['ordered', 'start'])
        ->and($settings('core/list-item'))->toBe(['text'])
        ->and($settings('core/quote'))->toBe(['text', 'citation'])
        ->and($settings('core/code'))->toBe(['code', 'language'])
        ->and($settings('core/table'))->toBe(['header', 'rows', 'caption'])
        ->and($settings('core/separator'))->toBe([])
        ->and($settings('core/spacer'))->toBe(['height'])
        ->and($type('core/table')->settingsSchema['properties']['rows']['items']['items']['x-control'])->toBe('rich-text')
        ->and($type('core/paragraph')->settingsSchema['properties']['text'])
        ->toBe(['type' => 'string', 'default' => '', 'x-control' => 'rich-text', 'title' => 'Text'])
        ->and($type('core/heading')->catalogue->category)->toBe('text');
});

test('a heading gets an id from its anchor, or from its text', function (array $settings, string $id) {
    expect(resolved(block('h', 'core/heading', $settings))->view->values['id'])->toBe($id);
})->with([
    'anchor' => [['anchor' => 'intro', 'text' => 'Whatever'], 'intro'],
    'text' => [['text' => 'Hello, <em>world</em> &amp; more'], 'hello-world-more'],
    'digits first' => [['text' => '2026 plans'], 'h-2026-plans'],
    'empty' => [['text' => ''], 'heading'],
    'long' => [['text' => str_repeat('a', 100)], str_repeat('a', 64)],
    'accents' => [['text' => 'Café crème'], 'caf-cr-me'],
]);

test('a heading shows its level and text', function () {
    $view = resolved(block('h', 'core/heading', ['level' => 3, 'text' => 'Hi']))->view->values;

    expect([$view['level'], $view['text']])->toBe([3, 'Hi']);
});

test('a list item goes only in a list, and a list may sit in a list item', function () {
    $placement = textContainer()->get(PlacementRulesInterface::class);
    $decide = fn (string $child, ?string $parent) => $placement->decide(
        BlockName::fromString($child),
        $parent === null ? null : BlockName::fromString($parent),
    )->allowed;

    expect($decide('core/list-item', 'core/list'))->toBeTrue()
        ->and($decide('core/list', 'core/list-item'))->toBeTrue()
        ->and($decide('core/list-item', null))->toBeFalse()
        ->and($decide('core/paragraph', 'core/list'))->toBeFalse()
        ->and($decide('core/paragraph', 'core/list-item'))->toBeFalse();
});

test('settings outside the rules are refused when edited', function (string $name, array $settings) {
    $editor = textContainer()->get(DocumentEditorInterface::class);

    expect(refusals($editor->insert(doc(), BlockName::fromString($name), Position::root(0), $settings)))
        ->toContain(EditRefusalKind::InvalidSettings);
})->with([
    ['core/heading', ['level' => 7]],
    ['core/heading', ['level' => 0]],
    ['core/heading', ['anchor' => 'Has Spaces']],
    ['core/heading', ['anchor' => '"><script>']],
    ['core/code', ['language' => '"><script>']],
    ['core/spacer', ['height' => '1px;} x{']],
    ['core/table', ['rows' => 'nope']],
    ['core/table', ['rows' => [[5]]]],
    ['core/list', ['start' => 0]],
]);

test('ordinary settings are accepted', function (string $name, array $settings) {
    $editor = textContainer()->get(DocumentEditorInterface::class);

    expect($editor->insert(doc(), BlockName::fromString($name), Position::root(0), $settings)->isAccepted())->toBeTrue();
})->with([
    ['core/heading', ['level' => 1, 'text' => '<strong>Hi</strong>', 'anchor' => 'top']],
    ['core/code', ['code' => '<?php echo 1;', 'language' => 'php']],
    ['core/spacer', ['height' => ['base' => '1rem', 'desktop' => '4rem']]],
    ['core/table', ['rows' => [['a', 'b'], ['c']], 'header' => false, 'caption' => 'x']],
    ['core/separator', []],
]);

test('a heading takes its id from the page when the page gives one, and makes its own otherwise', function () {
    $heading = textContainer()->get(\SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface::class)
        ->get(\SpeedySpec\Block\Domain\ValueObjects\BlockName::fromString('core/heading'));
    $behaviour = new \SpeedySpec\Block\Text\Domain\HeadingBehaviour();
    $block = block('h', 'core/heading', ['text' => 'Intro']);
    $given = \SpeedySpec\Block\Domain\ValueObjects\Context::empty()->with('core/heading-ids', ['h' => 'intro-2']);
    $front = \SpeedySpec\Block\Domain\ValueObjects\Mode::Front;
    $view = fn (\SpeedySpec\Block\Domain\ValueObjects\Context $context): string => $behaviour
        ->view($block, $heading, ['text' => 'Intro', 'anchor' => ''], $context, $front)
        ->values['id'];

    expect($heading->uses)->toBe(['core/heading-ids'])
        ->and($view($given))->toBe('intro-2')
        ->and($view(\SpeedySpec\Block\Domain\ValueObjects\Context::empty()))->toBe('intro')
        ->and(\SpeedySpec\Block\Text\Domain\HeadingBehaviour::idFor('Intro'))->toBe('intro');
});
