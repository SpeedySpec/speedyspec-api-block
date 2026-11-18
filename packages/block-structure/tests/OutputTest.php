<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\IssueKind;

test('a section is a plain container with a chosen element and an optional label', function () {
    $child = block('e', 'core/element', ['tag' => 'p', 'text' => 'Hello']);

    [$plain] = page(block('s', 'core/section', [], $child));
    [$labelled] = page(block('s', 'core/section', ['tag' => 'section', 'label' => 'Intro "one"'], $child));

    expect($plain)->toBe('<div><p>Hello</p></div>')
        ->and($labelled)->toBe('<section aria-label="Intro &quot;one&quot;"><p>Hello</p></section>');
});

test('an element draws its escaped text before its children', function () {
    $inner = block('i', 'core/element', ['tag' => 'span', 'text' => 'in']);

    [$html] = page(block('e', 'core/element', ['tag' => 'h2', 'text' => 'A <b>title</b> & '], $inner));
    [$default] = page(block('e', 'core/element'));

    expect($html)->toBe('<h2>A &lt;b&gt;title&lt;/b&gt; &amp; <span>in</span></h2>')
        ->and($default)->toBe('<div></div>');
});

test('an element or section refuses a tag outside its list', function (string $type, string $tag) {
    [$html, , $issues] = page(block('x', $type, ['tag' => $tag]));

    expect($html)->toBe('')->and($issues[0]->kind)->toBe(IssueKind::InvalidSettings);
})->with([
    ['core/element', 'script'],
    ['core/element', 'style'],
    ['core/element', 'iframe'],
    ['core/element', 'div onclick=x'],
    ['core/section', 'script'],
    ['core/section', 'p'],
]);

test('columns stack on small screens and sit side by side from a breakpoint', function () {
    $columns = block(
        'c',
        'core/columns',
        ['align' => 'center'],
        block('a', 'core/column', ['span' => 2]),
        block('b', 'core/column'),
    );

    [$html, $css] = page($columns);
    [$row, $two, $one] = [classOf($html, 0), classOf($html, 1), classOf($html, 2)];

    expect($html)->toBe("<div class=\"{$row}\"><div class=\"{$two}\"></div><div class=\"{$one}\"></div></div>")
        ->and($css)->toBe(
            // Children are drawn before their parent, so their rules are collected first.
            ".{$two}{flex:2 1 0%;min-width:0}"
            . ".{$one}{flex:1 1 0%;min-width:0}"
            . ".{$row}{display:flex;flex-direction:column}"
            . "@media (min-width:768px){.{$row}{flex-direction:row;align-items:center}}"
        );
});

test('columns can be told never to stack, or to stack below another breakpoint', function () {
    [$never, $neverCss] = page(block('c', 'core/columns', ['stackBelow' => '']));
    [$desktop, $desktopCss] = page(block('c', 'core/columns', ['stackBelow' => 'desktop']));

    expect($neverCss)->toBe('.' . classOf($never) . '{display:flex;flex-direction:row;align-items:stretch}')
        ->and($desktopCss)->toBe(
            '.' . classOf($desktop) . '{display:flex;flex-direction:column}'
            . '@media (min-width:1024px){.' . classOf($desktop) . '{flex-direction:row;align-items:stretch}}'
        );
});

test('a column can take a different share of the row at each breakpoint', function () {
    $column = block('a', 'core/column', ['span' => ['base' => 1, 'desktop' => 3]]);

    [$html, $css] = page(block('c', 'core/columns', ['stackBelow' => ''], $column));
    $class = classOf($html, 1);

    expect($css)->toContain(".{$class}{flex:1 1 0%;min-width:0}")
        ->toContain("@media (min-width:1024px){.{$class}{flex:3 1 0%}}");
});

test('a column only goes inside columns, and columns only hold columns', function () {
    [$stray, , $strayIssues] = page(block('a', 'core/column'));
    [$wrong, , $wrongIssues] = page(block('c', 'core/columns', [], block('e', 'core/element', ['text' => 'x'])));

    expect($stray)->toBe('')
        ->and($strayIssues[0]->kind)->toBe(IssueKind::DisallowedChild)
        ->and($wrong)->not->toContain('x')
        ->and($wrongIssues[0]->kind)->toBe(IssueKind::DisallowedChild);
});

test('a grid sets its number of columns, which may change by breakpoint', function () {
    $cell = fn (string $id) => block($id, 'core/element', ['text' => $id]);

    $columns = ['base' => 1, 'tablet' => 2, 'desktop' => 4];

    [$html, $css] = page(block('g', 'core/grid', ['columns' => $columns], $cell('a'), $cell('b')));
    $class = classOf($html);

    expect($html)->toBe("<div class=\"{$class}\"><div>a</div><div>b</div></div>")
        ->and($css)->toBe(
            ".{$class}{display:grid;grid-template-columns:repeat(1, minmax(0, 1fr))}"
            . "@media (min-width:768px){.{$class}{grid-template-columns:repeat(2, minmax(0, 1fr))}}"
            . "@media (min-width:1024px){.{$class}{grid-template-columns:repeat(4, minmax(0, 1fr))}}"
        );
});

test('a grid or column refuses a count outside one to twelve', function (string $type, string $setting, mixed $value) {
    $block = block('x', $type, [$setting => $value]);
    [, , $issues] = page($type === 'core/column' ? block('c', 'core/columns', [], $block) : $block);

    expect($issues)->not->toBe([])->and($issues[0]->kind)->toBe(IssueKind::InvalidSettings);
})->with([
    ['core/grid', 'columns', 0],
    ['core/grid', 'columns', 13],
    ['core/grid', 'columns', '3'],
    ['core/grid', 'columns', ['tablet' => 99]],
    ['core/column', 'span', 0],
    ['core/column', 'span', ['desktop' => 'wide']],
]);

test('blocks laid out alike share their rules', function () {
    [$html, $css] = page(block('g', 'core/grid', ['columns' => 3]), block('h', 'core/grid', ['columns' => 3]));

    expect(classOf($html, 0))->toBe(classOf($html, 1))
        ->and(substr_count($css, 'grid-template-columns'))->toBe(1);
});

test('columns still sit side by side when the host has other breakpoints', function () {
    $draw = function (array $settings, array $breakpoints): string {
        $container = new \SpeedySpec\Block\Domain\BlockServiceContainer();
        (new \SpeedySpec\Block\Infra\Memory\MemoryServiceProvider())->register($container);
        (new \SpeedySpec\Block\App\AppServiceProvider())->register($container);
        $registry = $container->get(\SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface::class);
        $registry->remove('tablet');
        $registry->remove('desktop');

        foreach ($breakpoints as $name => $width)
        {
            $registry->add(new \SpeedySpec\Block\Domain\ValueObjects\Breakpoint($name, $width));
        }

        $loader = $container->get(\SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface::class);
        $loader->load(new \SpeedySpec\Block\Structure\Domain\StructureTypesPackage());
        $loader->load(\SpeedySpec\Block\Structure\StructurePackage::from($container));
        $container->get(\SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface::class)->render(
            new \SpeedySpec\Block\Domain\Entities\Document(
                'd',
                'page',
                new \SpeedySpec\Block\Domain\Entities\BlockTree(block('c', 'core/columns', $settings)),
            ),
            new \SpeedySpec\Block\Domain\ValueObjects\RenderRequest(),
        );

        return $container->get(\SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface::class)->css();
    };

    expect($draw([], ['md' => 800, 'lg' => 1200]))
        ->toContain('@media (min-width:800px)')
        ->toContain('flex-direction:row')
        ->and($draw(['stackBelow' => 'nope'], ['md' => 800]))->toContain('@media (min-width:800px)')
        ->and($draw(['stackBelow' => 'lg'], ['md' => 800, 'lg' => 1200]))->toContain('@media (min-width:1200px)')
        ->and($draw([], []))->toContain('display:flex;flex-direction:row')->not->toContain('@media');
});
