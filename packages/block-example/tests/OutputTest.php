<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

test('a notice draws its tone and escaped text', function () {
    expect(html(block('n', 'example/notice', ['text' => 'Mind the <gap>', 'tone' => 'warning'])))
        ->toBe('<div class="notice notice--warning">Mind the &lt;gap&gt;</div>');
});

test('a notice defaults to the info tone and empty text', function () {
    expect(html(block('n', 'example/notice')))->toBe('<div class="notice notice--info"></div>');
});

test('the internal note appears in the admin output only', function () {
    $notice = block('n', 'example/notice', ['text' => 'Hi', 'internalNote' => 'Reviewed by legal']);

    expect(html($notice, Mode::Front))->not->toContain('Reviewed by legal')
        ->and(html($notice, Mode::Admin))
        ->toBe('<div class="notice notice--info">Hi</div><p class="notice__note">Reviewed by legal</p>');
});

test('the admin output of a notice without a note is the same as the front output', function () {
    $notice = block('n', 'example/notice', ['text' => 'Hi']);

    expect(html($notice, Mode::Admin))->toBe(html($notice, Mode::Front));
});

test('a list draws its items in order and switches element when ordered', function () {
    $items = [block('a', 'example/list-item', ['text' => 'One']), block('b', 'example/list-item', ['text' => 'Two'])];

    expect(html(block('l', 'example/list', ['label' => 'Steps'], ...$items)))
        ->toBe('<ul aria-label="Steps"><li>One</li><li>Two</li></ul>')
        ->and(html(block('l', 'example/list', ['ordered' => true], ...$items)))
        ->toBe('<ol><li>One</li><li>Two</li></ol>');
});

test('a list knows how many items it holds and tells them whether it is ordered', function () {
    $list = block('l', 'example/list', ['ordered' => true], block('a', 'example/list-item'), block('b', 'example/list-item'));
    $resolved = rendered($list)->resolved;

    expect($resolved->find('l')->view->values['count'])->toBe(2)
        ->and($resolved->find('a')->context->get('example/list-ordered'))->toBeTrue();
});

test('a list item outside a list is not drawn', function () {
    $result = rendered(block('a', 'example/list-item', ['text' => 'Loose']));

    expect($result->output)->toBe('')->and($result->issues[0]->kind)->toBe(IssueKind::DisallowedChild);
});

test('the package names the assets a page with a notice needs', function () {
    expect(rendered(block('n', 'example/notice'))->assets)->toBe(['example-notice']);
});
