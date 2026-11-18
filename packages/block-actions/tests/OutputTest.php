<?php

declare(strict_types=1);

use SpeedySpec\Block\Actions\ActionsStylesheet;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

function button(array $settings): Block
{
    return block('b', 'core/button', $settings);
}

test('each actions block draws its markup', function (Block $block, string $html) {
    expect(page($block))->toBe($html);
})->with([
    'button' => [
        button(['text' => 'Go & see', 'link' => '/start']),
        '<a class="b-button b-button--primary b-button--medium" href="/start">Go &amp; see</a>',
    ],
    'outline button in a new tab' => [
        button([
            'text' => 'Docs',
            'link' => 'https://e.test/docs',
            'newTab' => true,
            'variant' => 'outline',
            'size' => 'large',
        ]),
        '<a class="b-button b-button--outline b-button--large" href="https://e.test/docs" target="_blank"'
        . ' rel="noopener noreferrer">Docs</a>',
    ],
    'buttons' => [
        block('r', 'core/buttons', ['align' => 'center'], button(['text' => 'A', 'link' => '/a'])),
        '<div class="b-buttons b-buttons--center"><a class="b-button b-button--primary b-button--medium"'
        . ' href="/a">A</a></div>',
    ],
    'call to action' => [
        block('c', 'core/call-to-action', ['heading' => 'Join <us>', 'level' => 3, 'text' => 'It is <em>quick</em>.']),
        '<section class="b-cta"><h3>Join &lt;us&gt;</h3><div class="b-cta-text">It is <em>quick</em>.</div></section>',
    ],
    'call to action without text' => [
        block('c', 'core/call-to-action', ['heading' => 'Hi']),
        '<section class="b-cta"><h2>Hi</h2></section>',
    ],
    'testimonial with a photo' => [
        block('t', 'core/testimonial', ['quote' => 'Great', 'name' => 'Ann', 'role' => 'Designer', 'media' => 'face']),
        '<figure class="b-testimonial"><blockquote>Great</blockquote><figcaption><img src="https://e.test/face.jpg"'
        . ' width="96" height="96" alt="" loading="lazy"><span class="b-testimonial-name">Ann</span>'
        . '<span class="b-testimonial-role">Designer</span></figcaption></figure>',
    ],
    'testimonial without photo or role' => [
        block('t', 'core/testimonial', ['quote' => 'Great', 'name' => 'Ann']),
        '<figure class="b-testimonial"><blockquote>Great</blockquote><figcaption>'
        . '<span class="b-testimonial-name">Ann</span></figcaption></figure>',
    ],
    'social links' => [
        block('s', 'core/social-links', ['size' => 'small'], block('g', 'core/social-link', [
            'service' => 'github',
            'url' => 'https://github.com/a',
        ])),
        '<ul class="b-social b-social--small"><li><a class="b-social-link" href="https://github.com/a"'
        . ' rel="me noopener">GitHub</a></li></ul>',
    ],
]);

test('a pricing table lays its plans out by width, and a plan shows what it includes', function () {
    $plan = block('p', 'core/pricing-plan', [
        'name' => 'Pro',
        'price' => '$19',
        'period' => 'per month',
        'description' => 'For <strong>teams</strong>',
        'features' => ['Ten sites', '', '<b>Priority</b> help'],
        'highlighted' => true,
        'badge' => 'Most popular',
    ], button(['text' => 'Buy', 'link' => '/buy']));
    [$result, $css] = rendered([block('t', 'core/pricing-table', ['columns' => ['base' => 1, 'tablet' => 3]], $plan)]);

    expect($result->output)->toMatch('#^<div class="b-pricing b-[a-z0-9]+">#')
        ->and($result->output)->toContain(
            '<article class="b-plan b-plan--highlighted"><p class="b-plan-badge">Most popular</p><h3>Pro</h3>'
            . '<p class="b-plan-price"><span>$19</span> <span>per month</span></p>'
            . '<div class="b-plan-description">For <strong>teams</strong></div>'
            . '<ul class="b-plan-features"><li>Ten sites</li><li>&lt;b&gt;Priority&lt;/b&gt; help</li></ul>'
            . '<a class="b-button b-button--primary b-button--medium" href="/buy">Buy</a></article>',
        )
        ->and($css)->toContain('grid-template-columns:repeat(1, minmax(0, 1fr))')
        ->and($css)->toContain('repeat(3, minmax(0, 1fr))');
});

test('while editing, each part of a plan can be typed into, empty features included', function () {
    $plan = block('p', 'core/pricing-plan', ['features' => ['A', '']]);
    $html = page(block('t', 'core/pricing-table', [], $plan), true);

    expect($html)->toContain('<h3 data-block-setting="name">Plan</h3>')
        ->and($html)->toContain('<span data-block-setting="price">$0</span>')
        ->and($html)->toContain('<li data-block-setting="features.0">A</li><li data-block-setting="features.1"></li>')
        ->and($html)->toContain('<div class="b-plan-description" data-block-setting="description"></div>');
});

test('a rating says its score in words, with the stars only as decoration', function () {
    $html = page(block('r', 'core/rating', ['value' => 3.5, 'outOf' => 5]));

    expect($html)->toStartWith('<p class="b-rating"><span class="b-rating-stars" aria-hidden="true">')
        ->and(substr_count($html, '<svg'))->toBe(5)
        ->and(substr_count($html, 'class="b-star b-star--full"'))->toBe(3)
        ->and(substr_count($html, 'class="b-star b-star--half"'))->toBe(1)
        ->and(substr_count($html, 'class="b-star b-star--empty"'))->toBe(1)
        ->and($html)->toEndWith('</span><span class="b-rating-text">Rated 3.5 out of 5</span></p>');
});

test('a button or social link without a usable link is not set up', function (string $link) {
    $social = fn (): Block => block('s', 'core/social-links', [], block('g', 'core/social-link', [
        'service' => 'website',
        'url' => $link,
    ]));

    expect(page(button(['text' => 'Go', 'link' => $link])))->toBe('')
        ->and(page(button(['text' => 'Go', 'link' => $link]), true))
        ->toBe('<span class="b-button b-button--primary b-button--medium" aria-disabled="true">'
            . '<span data-block-setting="text">Go</span>'
            . '<span class="b-note">Add a link in the block\'s settings.</span></span>')
        ->and(page($social()))->not->toContain('href')
        ->and(page($social()))->not->toContain('<li');
})->with([
    'a script' => ['javascript:alert(1)'],
    'a script in odd case with a space' => [' JaVaScRiPt:alert(1)'],
    'a data address' => ['data:text/html,<script>x</script>'],
    'nothing' => [''],
]);

test('a social link not set up shows editors what it needs', function () {
    $html = page(block('s', 'core/social-links', [], block('g', 'core/social-link', ['service' => 'github'])), true);

    expect($html)->toContain('<span class="b-social-link" aria-disabled="true">GitHub</span>')
        ->and($html)->toContain('<span class="b-note">Add a link in the block\'s settings.</span>');
});

test('the shared style settings go on each block\'s outer element', function (
    string $type,
    array $settings,
    string $tag,
) {
    $styled = new Block('x', BlockName::fromString($type), 1, $settings, ['core/style' => ['textAlign' => 'center']]);
    $html = $type === 'core/social-link'
        ? page(block('l', 'core/social-links', [], $styled))
        : page($type === 'core/pricing-plan' ? block('t', 'core/pricing-table', [], $styled) : $styled);

    expect($html)->toMatch('#<' . $tag . ' class="[^"]*b-[a-z0-9]{10}[^"]*"#');
})->with([
    ['core/button', ['link' => '/a'], 'a'],
    ['core/buttons', [], 'div'],
    ['core/call-to-action', [], 'section'],
    ['core/testimonial', [], 'figure'],
    ['core/pricing-plan', [], 'article'],
    ['core/rating', [], 'p'],
    ['core/social-links', [], 'ul'],
    ['core/social-link', ['url' => 'https://e.test'], 'li'],
]);

test('every block brings the actions stylesheet, listed once per page', function () {
    [$result] = rendered([
        button(['link' => '/a']),
        block('r', 'core/rating'),
        block('s', 'core/social-links'),
    ]);

    expect(array_count_values($result->assets)['core/actions'] ?? 0)->toBe(1);
});

test('the stylesheet loads nothing else and keeps focus visible', function () {
    expect(ActionsStylesheet::CSS)->not->toContain('@import')
        ->and(ActionsStylesheet::CSS)->not->toContain('url(')
        ->and(ActionsStylesheet::CSS)->toContain(':focus-visible')
        ->and(ActionsStylesheet::CSS)->toContain('prefers-reduced-motion')
        ->and(ActionsStylesheet::CSS)->not->toContain('outline: none')
        ->and(ActionsStylesheet::CSS)->toContain('var(--color-primary, #2456d6)');
});
