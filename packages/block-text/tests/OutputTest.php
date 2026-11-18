<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;

test('each text block draws its markup', function (Block $block, string $html) {
    expect(page($block))->toBe($html);
})->with([
    'heading' => [block('h', 'core/heading', ['level' => 3, 'text' => 'Hi']), '<h3 id="hi">Hi</h3>'],
    'heading with anchor' => [block('h', 'core/heading', ['text' => 'Hi', 'anchor' => 'top']), '<h2 id="top">Hi</h2>'],
    'paragraph' => [block('p', 'core/paragraph', ['text' => 'A <em>b</em>']), '<p>A <em>b</em></p>'],
    'empty paragraph' => [block('p', 'core/paragraph'), '<p></p>'],
    'bulleted list' => [
        block('l', 'core/list', [], block('a', 'core/list-item', ['text' => 'a']), block('b', 'core/list-item', ['text' => 'b'])),
        '<ul><li>a</li><li>b</li></ul>',
    ],
    'numbered list from three' => [
        block('l', 'core/list', ['ordered' => true, 'start' => 3], block('i', 'core/list-item', ['text' => 'a'])),
        '<ol start="3"><li>a</li></ol>',
    ],
    'numbered list from one' => [
        block('l', 'core/list', ['ordered' => true], block('i', 'core/list-item', ['text' => 'a'])),
        '<ol><li>a</li></ol>',
    ],
    'nested list' => [
        block('l', 'core/list', [], block('i', 'core/list-item', ['text' => 'a'], block('n', 'core/list', [], block('j', 'core/list-item', ['text' => 'b'])))),
        '<ul><li>a<ul><li>b</li></ul></li></ul>',
    ],
    'quote' => [
        block('q', 'core/quote', ['text' => 'Less', 'citation' => 'Rams & co']),
        '<blockquote><p>Less</p><cite>Rams &amp; co</cite></blockquote>',
    ],
    'quote without citation' => [block('q', 'core/quote', ['text' => 'a']), '<blockquote><p>a</p></blockquote>'],
    'code' => [
        block('c', 'core/code', ['code' => 'echo 1;', 'language' => 'php']),
        '<pre><code class="language-php">echo 1;</code></pre>',
    ],
    'table' => [
        block('t', 'core/table', ['rows' => [['Name', 'Price'], ['Tea', '3']], 'caption' => 'Prices']),
        '<figure><table><thead><tr><th scope="col">Name</th><th scope="col">Price</th></tr></thead>'
        . '<tbody><tr><td>Tea</td><td>3</td></tr></tbody></table><figcaption>Prices</figcaption></figure>',
    ],
    'table without header' => [
        block('t', 'core/table', ['rows' => [['a']], 'header' => false]),
        '<figure><table><tbody><tr><td>a</td></tr></tbody></table></figure>',
    ],
    'separator' => [block('s', 'core/separator'), '<hr>'],
]);

test('formatted text is cleaned and plain text is escaped', function () {
    expect(page(block('p', 'core/paragraph', ['text' => '<em>a</em><script>x</script>'])))->toBe('<p><em>a</em>x</p>')
        ->and(page(block('c', 'core/code', ['code' => '<b>&</b>'])))->toBe('<pre><code>&lt;b&gt;&amp;&lt;/b&gt;</code></pre>')
        ->and(page(block('q', 'core/quote', ['citation' => '<b>x</b>'])))->toContain('<cite>&lt;b&gt;x&lt;/b&gt;</cite>');
});

test('a ragged or empty table draws what it can, and a broken one is reported, never thrown', function () {
    [$ragged] = rendered([block('t', 'core/table', ['rows' => [['a', 'b', 'c'], ['d']]])]);
    [$broken] = rendered([block('t', 'core/table', ['rows' => [['a'], 'x']])]);

    expect($ragged->output)->toContain('<tr><td>d</td><td></td><td></td></tr>')
        ->and(page(block('t', 'core/table', ['rows' => []])))->toBe('<figure><table></table></figure>')
        ->and($broken->output)->toBe('')
        ->and($broken->issues[0]->kind)->toBe(IssueKind::InvalidSettings);
});

test('while editing, typeable settings are marked, and visitors never see the marks', function () {
    $table = block('t', 'core/table', ['rows' => [['a'], ['b']], 'caption' => '']);
    $quote = block('q', 'core/quote', ['text' => 'a']);
    $item = block('l', 'core/list', [], block('i', 'core/list-item', ['text' => 'a'], block('n', 'core/list', [], block('j', 'core/list-item', ['text' => 'b']))));

    expect(page($table, true))->toContain('<td data-block-setting="rows.1.0">b</td>')
        ->toContain('<th scope="col" data-block-setting="rows.0.0">a</th>')
        ->toContain('<figcaption data-block-setting="caption"></figcaption>')
        ->and(page($quote, true))->toContain('<p data-block-setting="text">a</p>')->toContain('<cite data-block-setting="citation"></cite>')
        ->and(page(block('h', 'core/heading', ['text' => 'Hi']), true))->toContain('id="hi"')->toContain('data-block-setting="text"')
        ->and(page($item, true))->toContain('<span data-block-setting="text">a</span><ul')
        ->and(page($table))->not->toContain('data-block-setting')
        ->and(page($quote))->not->toContain('data-block-setting');
});

test('a spacer\'s height is a style rule that may differ by width', function () {
    [$result, $css] = rendered([block('s', 'core/spacer', ['height' => ['base' => '2rem', 'tablet' => '4rem']])]);
    preg_match('/class="(b-[0-9a-f]{10})"/', $result->output, $found);
    $class = $found[1] ?? 'none';

    expect($result->output)->toBe("<div class=\"{$class}\" aria-hidden=\"true\"></div>")
        ->and($css)->toContain(".{$class}{height:2rem}")
        ->toContain("@media (min-width:768px){.{$class}{height:4rem}}")
        ->and($result->issues)->toBe([]);
});

test('a spacer\'s stored height that does not fit is left out of the stylesheet', function () {
    [, $css] = rendered([block('s', 'core/spacer', ['height' => '1px;} body{display:none'])]);

    expect($css)->not->toContain('display');
});

test('a list item outside a list is reported, as its definition says', function () {
    [$result] = rendered([block('i', 'core/list-item', ['text' => 'a'])]);

    expect($result->output)->toBe('')->and($result->issues[0]->kind)->toBe(IssueKind::DisallowedChild);
});
