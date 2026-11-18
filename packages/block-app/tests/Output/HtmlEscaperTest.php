<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Output\HtmlEscaper;
use SpeedySpec\Block\App\Output\OutputEscaperRegistry;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedTargetException;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;

use function SpeedySpec\Block\ContractTests\outputEscaperContract;

describe('HtmlEscaper', function () {
    outputEscaperContract(fn () => new HtmlEscaper());
});

test('the html escaper names its target', function () {
    expect((new HtmlEscaper())->target())->toBe('html');
});

test('text and attribute values are encoded, including existing entities', function () {
    $escaper = new HtmlEscaper();

    expect($escaper->text('<b>Tom & "Jerry" &amp;</b>'))
        ->toBe('&lt;b&gt;Tom &amp; &quot;Jerry&quot; &amp;amp;&lt;/b&gt;')
        ->and($escaper->attribute("it's"))->toBe('it&apos;s');
});

test('invalid utf-8 is substituted, not dropped', function () {
    expect((new HtmlEscaper())->text("a\xC3\x28b"))->not->toBe('');
});

test('urls keep safe schemes and relative forms and drop everything else', function (string $in, string $out) {
    expect((new HtmlEscaper())->url($in))->toBe($out);
})->with([
    ['https://example.test/a?b=1&c=2', 'https://example.test/a?b=1&amp;c=2'],
    ['HTTP://example.test/', 'HTTP://example.test/'],
    ['/path', '/path'],
    ['path/to?x=1', 'path/to?x=1'],
    ['#top', '#top'],
    ['?q=1', '?q=1'],
    ['//cdn.example.test/x', '//cdn.example.test/x'],
    ['mailto:a@example.test', 'mailto:a@example.test'],
    ['tel:+15550100', 'tel:+15550100'],
    ['', ''],
    ['javascript:alert(1)', ''],
    ['JaVaScRiPt:alert(1)', ''],
    ["java\nscript:alert(1)", ''],
    ["java\tscript:alert(1)", ''],
    [' javascript:alert(1)', ''],
    ["\x01javascript:alert(1)", ''],
    ['data:text/html,<script>', ''],
    ['vbscript:x', ''],
    ['/a"onmouseover="x', '/a&quot;onmouseover=&quot;x'],
]);

test('attributes render in a fixed order with every value escaped', function () {
    $bag = AttributeBag::empty()->withAttribute('data-x', '"><script>')->withClass('a', 'b')->withStyle('color', 'red')
        ->withStyle('margin', '0')->withId('top')->withAttribute('aria-label', 'A & B');

    expect((new HtmlEscaper())->attributes($bag))
        ->toBe(' id="top" class="a b" style="color: red; margin: 0" data-x="&quot;&gt;&lt;script&gt;" aria-label="A &amp; B"');
});

test('an empty bag renders nothing and a class-only bag renders only the class', function () {
    $escaper = new HtmlEscaper();

    expect($escaper->attributes(AttributeBag::empty()))->toBe('')
        ->and($escaper->attributes(AttributeBag::empty()->withClass('a')))->toBe(' class="a"');
});

test('the escaper registry replaces by target and throws for an unknown target', function () {
    $registry = new OutputEscaperRegistry();
    $registry->add(new HtmlEscaper());
    $mine = new HtmlEscaper();
    $registry->add($mine);

    expect($registry->get('html'))->toBe($mine)
        ->and($registry->has('html'))->toBeTrue()
        ->and($registry->has('pdf'))->toBeFalse()
        ->and(fn () => $registry->get('pdf'))->toThrow(UnsupportedTargetException::class);
});
