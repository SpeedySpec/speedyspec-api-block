<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Output\RichTextSanitizer;
use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\RichTextSanitizerInterface;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

test('only the allowed subset survives cleaning, and text is kept', function (string $in, string $out) {
    expect((new RichTextSanitizer())->clean($in))->toBe($out);
})->with([
    'marks' => ['<strong>a</strong> <em>b</em> <s>c</s> <code>d</code> <sub>e</sub> <sup>f</sup> <mark>g</mark>',
        '<strong>a</strong> <em>b</em> <s>c</s> <code>d</code> <sub>e</sub> <sup>f</sup> <mark>g</mark>'],
    'script' => ['<script>x()</script>ok', 'x()ok'],
    'event attribute' => ['<b onclick="x">a</b>', 'a'],
    'attribute on an allowed element' => ['<strong class="x" style="y">a</strong>', '<strong>a</strong>'],
    'script link' => ['<a href="javascript:x">a</a>', 'a'],
    'split script link' => ["<a href=\"java\tscript:x\">a</a>", 'a'],
    'data link' => ['<a href="data:text/html,x">a</a>', 'a'],
    'web link' => ['<a href="https://e.test/?q=1&amp;r=2" target="_blank">a</a>', '<a href="https://e.test/?q=1&amp;r=2">a</a>'],
    'mail link' => ['<a href="mailto:a@e.test">a</a>', '<a href="mailto:a@e.test">a</a>'],
    'site path' => ['<a href="/about">a</a>', '<a href="/about">a</a>'],
    'other site path' => ['<a href="//evil.test/x">a</a>', 'a'],
    'link without href' => ['<a>a</a>', 'a'],
    'misnested' => ['<em><strong>a</em></strong>', '<em><strong>a</strong></em>'],
    'escaped markup' => ['&lt;script&gt;', '&lt;script&gt;'],
    'quotes' => ['say "hi" & \'bye\'', 'say "hi" &amp; \'bye\''],
    'line breaks' => ['a<br>b<br/>', 'a<br>b<br>'],
    'comment' => ['<!-- c -->a', 'a'],
    'block element' => ['<div><p>a</p></div>', 'a'],
    'invalid utf-8' => ["\xC3\x28", "\u{FFFD}("],
    'empty' => ['', ''],
    'plain text' => ['just text', 'just text'],
]);

test('cleaning never throws and stays bounded on hostile input', function () {
    $sanitizer = new RichTextSanitizer();
    $deep = str_repeat('<em>', 5000) . 'x' . str_repeat('</em>', 5000);

    expect($sanitizer->clean($deep))->toContain('x')
        ->and(substr_count($sanitizer->clean($deep), '<em>'))->toBeLessThanOrEqual(32)
        ->and($sanitizer->clean(str_repeat('<strong>a', 20000)))->toContain('a');
});

$formatted = ['type' => 'object', 'properties' => [
    'text' => ['type' => 'string', 'default' => '', 'x-control' => 'rich-text'],
    'plain' => ['type' => 'string', 'default' => ''],
]];
$draw = function (string $tag) {
    return new class($tag) implements \SpeedySpec\Block\Domain\Contracts\BlockOutputInterface {
        public function __construct(private readonly string $tag)
        {
        }

        public function render(RenderInput $input): string
        {
            return sprintf('<%1$s>%2$s</%1$s>', $this->tag, $input->escaper->richText((string) $input->view->values['text']));
        }
    };
};

test('the editor stores formatted text already cleaned, and leaves plain text alone', function () use ($formatted) {
    [$editor] = editor(fn ($c) => types($c)->add(type('acme/p', ['settings' => $formatted])));
    $settings = ['text' => '<strong onclick="x">a</strong><script>b</script>', 'plain' => '<b>kept</b>'];

    $inserted = $editor->insert(doc(), BlockName::fromString('acme/p'), Position::root(0), $settings);
    $id = $inserted->affectedIds[0];
    $updated = $editor->updateSettings($inserted->document, $id, $settings);
    $pasted = $editor->insertBlocks(doc(), [block('p', 'acme/p', $settings)], Position::root(0));

    expect($inserted->document->tree->find($id)->settings)->toBe(['text' => '<strong>a</strong>b', 'plain' => '<b>kept</b>'])
        ->and($updated->document->tree->find($id)->settings['text'])->toBe('<strong>a</strong>b')
        ->and($pasted->document->tree->roots()[0]->settings['text'])->toBe('<strong>a</strong>b')
        ->and($editor->restore(doc(), [block('p', 'acme/p', $settings)])->document->tree->find('p')->settings['text'])
        ->toBe('<strong>a</strong>b');
});

test('stored formatted text that was never cleaned is cleaned when drawn', function () use ($formatted, $draw) {
    [$renderer] = renderer(function ($c) use ($formatted, $draw) {
        types($c)->add(type('acme/p', ['settings' => $formatted]));
        outputs($c)->set(BlockName::fromString('acme/p'), $draw('p'));
    });

    expect($renderer->render(doc(block('p', 'acme/p', ['text' => '<em>a</em><img src=x onerror=1>'])), new RenderRequest())->output)
        ->toBe('<p><em>a</em></p>');
});

test('a bound value into formatted text is drawn as text, whatever the source returns', function () use (
    $formatted,
    $draw,
) {
    [$renderer] = renderer(function ($c) use ($formatted, $draw) {
        types($c)->add(type('acme/p', ['settings' => $formatted]));
        outputs($c)->set(BlockName::fromString('acme/p'), $draw('p'));
        sources($c)->add(new class implements DataSourceInterface {
            public function name(): string
            {
                return 'acme/evil';
            }

            public function argumentsSchema(): array
            {
                return ['type' => 'object'];
            }

            public function valueSchema(): array
            {
                return [];
            }

            public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
            {
                return DataSourceResult::value('<img src=x onerror=1> & <strong>b</strong>');
            }
        });
    });
    $path = SettingPath::fromString('text');
    $bound = block('p', 'acme/p')->withBinding($path, Binding::to('acme/evil'));
    $fallback = block('q', 'acme/p')->withBinding($path, Binding::to('acme/missing')->withFallback('<b>fb</b>'));

    expect($renderer->render(doc($bound), new RenderRequest())->output)
        ->toBe('<p>&lt;img src=x onerror=1&gt; &amp; &lt;strong&gt;b&lt;/strong&gt;</p>')
        ->and($renderer->render(doc($fallback), new RenderRequest())->output)->toBe('<p>&lt;b&gt;fb&lt;/b&gt;</p>');
});

test('the sanitizer is a contract of its own and can be replaced', function () {
    [, $c] = renderer(fn ($c) => $c->add(RichTextSanitizerInterface::class, fn () => new class implements RichTextSanitizerInterface {
        public function clean(string $html): string
        {
            return 'replaced';
        }
    }));

    expect($c->get(\SpeedySpec\Block\Domain\Contracts\OutputEscaperRegistryInterface::class)->get('html')->richText('<b>x</b>'))
        ->toBe('replaced');
});
