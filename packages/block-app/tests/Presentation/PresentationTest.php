<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusalKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

$host = function (?callable $setup = null): array {
    [$renderer, $c] = renderer(function (BlockServiceContainer $c) use ($setup) {
        $c->get(BlockPackageLoaderInterface::class)->load(PresentationPackage::from($c));
        types($c)->add(type('acme/box', ['children' => true]));
        outputs($c)->set(BlockName::fromString('acme/box'), tag('div'));

        if ($setup !== null)
        {
            $setup($c);
        }
    });

    return [$renderer, $c, $c->get(StyleCollectorInterface::class)];
};
$styled = fn (string $id, array $style) => block($id, 'acme/box')->withExtension('core/style', $style);

test('the default breakpoints are tablet and desktop, listed narrowest first whatever the order added', function () {
    [, $c] = renderer();
    $breakpoints = $c->get(BreakpointRegistryInterface::class);
    $breakpoints->add(new Breakpoint('wide', 1440));
    $breakpoints->add(new Breakpoint('phablet', 480));
    $breakpoints->remove('desktop');

    expect(array_map(fn (Breakpoint $b) => [$b->name, $b->minWidth], $breakpoints->all()))
        ->toBe([['phablet', 480], ['tablet', 768], ['wide', 1440]])
        ->and($breakpoints->get('tablet')->minWidth)->toBe(768)
        ->and($breakpoints->get('desktop'))->toBeNull();
});

test('design tokens are named values that become custom properties', function () {
    [, $c] = renderer();
    $tokens = $c->get(DesignTokenRegistryInterface::class);
    $tokens->set('color.primary', '#0055ff');
    $tokens->set('space.md', '1rem');
    $tokens->set('color.primary', '#112233');
    $tokens->set('gone', 'x');
    $tokens->remove('gone');

    expect($tokens->all())->toBe(['color.primary' => '#112233', 'space.md' => '1rem'])
        ->and($tokens->get('space.md'))->toBe('1rem')
        ->and($tokens->get('gone'))->toBeNull()
        ->and($c->get(StyleCollectorInterface::class)->css())
        ->toBe(':root{--color-primary:#112233;--space-md:1rem}');
});

test('a token refuses a name or value that could break out of the stylesheet', function (string $name, string $value) {
    [, $c] = renderer();

    expect(fn () => $c->get(DesignTokenRegistryInterface::class)->set($name, $value))
        ->toThrow(InvalidStyleException::class);
})->with([
    ['color.primary', 'red;} body{display:none'],
    ['color.primary', 'url(//evil.example)'],
    ['color}primary', 'red'],
    ['Color', 'red'],
    ['', 'red'],
]);

test('the collector gives equal rules one class and writes media queries narrowest first', function () use ($host) {
    [, , $styles] = $host();
    $first = $styles->add(new StyleRule(['padding' => '1rem']), new StyleRule(['padding' => '3rem'], 'desktop'));
    $again = $styles->add(new StyleRule(['padding' => '1rem']), new StyleRule(['padding' => '3rem'], 'desktop'));
    $other = $styles->add(new StyleRule(['color' => 'red'], 'tablet'), new StyleRule(['margin' => '0']));

    expect($first)->toMatch('/^b-[0-9a-f]{10}\z/')
        ->and($again)->toBe($first)
        ->and($other)->not->toBe($first)
        ->and($styles->css())->toBe(
            ".{$first}{padding:1rem}.{$other}{margin:0}"
            . "@media (min-width:768px){.{$other}{color:red}}"
            . "@media (min-width:1024px){.{$first}{padding:3rem}}"
        );

    $styles->clear();

    expect($styles->css())->toBe('');
});

test('a rule for a breakpoint nobody registered is left out of the stylesheet', function () use ($host) {
    [, , $styles] = $host();
    $class = $styles->add(new StyleRule(['color' => 'red']), new StyleRule(['color' => 'blue'], 'watch'));

    expect($styles->css())->toBe(".{$class}{color:red}");
});

test('a styled block gets a class, and its rules land in the stylesheet', function () use ($host, $styled) {
    [$renderer, $c, $styles] = $host(function ($c) {
        $c->get(DesignTokenRegistryInterface::class)->set('color.primary', '#0055ff');
    });
    $block = $styled('a', [
        'padding' => ['base' => '1rem', 'desktop' => '2rem 4rem'],
        'color' => 'token:color.primary',
        'textAlign' => 'center',
        'background' => '#fff',
    ]);

    $output = $renderer->render(doc($block), new RenderRequest())->output;
    preg_match('/class="(b-[0-9a-f]{10})"/', $output, $found);
    $class = $found[1] ?? 'none';

    expect($output)->toBe("<div class=\"{$class}\"></div>")
        ->and($styles->css())->toBe(
            ':root{--color-primary:#0055ff}'
            . ".{$class}{padding:1rem;text-align:center;color:var(--color-primary);background-color:#fff}"
            . "@media (min-width:1024px){.{$class}{padding:2rem 4rem}}"
        );
});

test('a block with no style settings gets no class and adds nothing', function () use ($host, $styled) {
    [$renderer, , $styles] = $host();

    $output = $renderer->render(doc(block('a', 'acme/box'), $styled('b', [])), new RenderRequest())->output;

    expect($output)->toBe('<div></div><div></div>')->and($styles->css())->toBe('');
});

test('two blocks styled alike share one class and one rule', function () use ($host, $styled) {
    [$renderer, , $styles] = $host();

    $output = $renderer->render(
        doc($styled('a', ['margin' => '0 auto']), $styled('b', ['margin' => '0 auto'])),
        new RenderRequest(Mode::Admin),
    )->output;

    expect(preg_match_all('/class="(b-[0-9a-f]{10})"/', $output, $found))->toBe(2)
        ->and($found[1][0])->toBe($found[1][1])
        ->and($styles->css())->toBe(".{$found[1][0]}{margin:0 auto}");
});

test('style values that are not in the vocabulary are refused when edited', function (array $style) use ($host) {
    [, $c] = $host();
    $editor = $c->get(DocumentEditorInterface::class);

    expect(refusals($editor->updateExtension(doc(block('a', 'acme/box')), 'a', 'core/style', $style)))
        ->not->toBe([])
        ->each->toBe(EditRefusalKind::InvalidSettings);
})->with([
    'injection' => [['color' => 'red;} body{display:none']],
    'url' => [['background' => 'url(//evil.example)']],
    'injection at a breakpoint' => [['padding' => ['tablet' => '1rem} x{']]],
    'unknown property' => [['position' => 'fixed']],
    'not an alignment' => [['textAlign' => 'middle']],
    'line break' => [['color' => "red\n"]],
    'five lengths' => [['margin' => '1px 1px 1px 1px 1px']],
]);

test('good style values are accepted when edited', function () use ($host) {
    [, $c] = $host();
    $editor = $c->get(DocumentEditorInterface::class);
    $style = [
        'padding' => '0',
        'margin' => ['base' => '0 auto', 'tablet' => '1.5rem 10% -2px token:space.md'],
        'gap' => 'token:space.md',
        'width' => '50%',
        'maxWidth' => '60ch',
        'minHeight' => '100vh',
        'textAlign' => ['base' => 'left', 'desktop' => 'center'],
        'fontSize' => '1.25rem',
        'fontWeight' => '700',
        'lineHeight' => '1.5',
        'color' => 'currentColor',
        'background' => '#FFAA0080',
        'borderRadius' => '4px 8px',
    ];

    expect($editor->updateExtension(doc(block('a', 'acme/box')), 'a', 'core/style', $style)->isAccepted())->toBeTrue();
});

test('stored style values that slipped past editing are dropped, not written to the stylesheet', function () use (
    $host,
    $styled,
) {
    [$renderer, , $styles] = $host();
    $block = $styled('a', ['color' => 'red;} body{display:none', 'margin' => '1rem']);

    $result = $renderer->render(doc($block), new RenderRequest(Mode::Admin));

    expect($styles->css())->not->toContain('display:none')->not->toContain('body');
});

test('styles are only collected for the html target', function () use ($host, $styled) {
    [$renderer, $c, $styles] = $host();
    $step = new \SpeedySpec\Block\App\Presentation\StyleStep(
        $styles,
    );
    $resolved = $c->get(\SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface::class)
        ->resolve(doc($styled('a', ['margin' => '0'])), Mode::Front);

    $bag = $step->attributes(
        \SpeedySpec\Block\Domain\ValueObjects\AttributeBag::empty(),
        $resolved->find('a'),
        new RenderRequest(Mode::Front, 'text'),
    );

    expect($bag->isEmpty())->toBeTrue()->and($styles->css())->toBe('');
});
