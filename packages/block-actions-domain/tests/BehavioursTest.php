<?php

declare(strict_types=1);

use SpeedySpec\Block\Actions\Domain\SocialServices;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;

function social(string $service, string $url): \SpeedySpec\Block\Domain\Entities\Block
{
    return block('s', 'core/social-link', ['service' => $service, 'url' => $url]);
}

test('a social link to another site is unavailable, naming the service', function (
    string $service,
    string $url,
    string $message,
) {
    $resolved = resolve([block('l', 'core/social-links', [], social($service, $url))]);

    expect($resolved->find('s')?->view)->toBeNull()
        ->and(issuesOf('s', $resolved))->toBe([[IssueKind::UnavailableMedia, $message, 'url']]);
})->with([
    'another site' => ['instagram', 'https://evil.test/instagram', 'This link is not to Instagram.'],
    'a lookalike host' => ['instagram', 'https://instagram.com.evil.test/x', 'This link is not to Instagram.'],
    'plain http' => ['instagram', 'http://instagram.com/x', 'This link is not to Instagram.'],
    'a user part' => ['github', 'https://github.com@evil.test/x', 'This link is not to GitHub.'],
    'a port' => ['github', 'https://github.com:8443/x', 'This link is not to GitHub.'],
    'a script' => ['website', 'javascript:alert(1)', 'This link is not to Website.'],
    'a website over http' => ['website', 'http://e.test', 'This link is not to Website.'],
    'email as a web link' => ['email', 'https://e.test', 'Use a mailto: link for email.'],
]);

test('a social link to its own service is shown with its name', function (string $service, string $url, string $title) {
    expect(viewOf(block('l', 'core/social-links', [], social($service, $url))))->not->toBeNull()
        ->and(resolve([block('l', 'core/social-links', [], social($service, $url))])->find('s')?->view?->values)
        ->toBe(['service' => $service, 'title' => $title, 'url' => $url]);
})->with([
    ['instagram', 'https://www.instagram.com/someone', 'Instagram'],
    ['x', 'https://twitter.com/someone', 'X'],
    ['github', 'https://github.com/someone', 'GitHub'],
    ['website', 'https://e.test', 'Website'],
    ['mastodon', 'https://any.social/@someone', 'Mastodon'],
    ['email', 'mailto:a@e.test', 'Email'],
]);

test('a social link with no link yet is not set up: an empty link and no issue', function () {
    $resolved = resolve([block('l', 'core/social-links', [], block('s', 'core/social-link', ['service' => 'github']))]);

    expect($resolved->find('s')?->view?->values['url'])->toBe('')
        ->and(issuesOf('s', $resolved))->toBe([]);
});

test('every service has a title and is checked', function () {
    foreach (SocialServices::NAMES as $service)
    {
        expect(SocialServices::title($service))->not->toBe('')
            ->and(SocialServices::accepts($service, 'ftp://e.test'))->toBeFalse();
    }
});

test('a testimonial shows its photo when the library has it, as an image', function () {
    $view = viewOf(block('t', 'core/testimonial', ['quote' => 'Great', 'name' => 'Ann', 'media' => 'face']));

    expect($view)->toMatchArray(['quote' => 'Great', 'name' => 'Ann', 'role' => ''])
        ->and($view['photo'])
        ->toBe(['url' => 'https://e.test/face.jpg', 'alt' => 'Ann', 'width' => 96, 'height' => 96]);
});

test('a testimonial without a usable photo is still shown, without one', function (string $media, bool $library) {
    $resolved = resolve([block('t', 'core/testimonial', ['quote' => 'Great', 'media' => $media])], $library);

    expect($resolved->find('t')?->view?->values['photo'])->toBeNull()->and(issuesOf('t', $resolved))->toBe([]);
})->with([
    'missing' => ['gone', true],
    'a PDF' => ['doc', true],
    'no library' => ['face', false],
    'none chosen' => ['', true],
]);

test('a rating is kept within its scale and rounded to a half', function (int|float $value, int|float $shown) {
    expect(viewOf(block('r', 'core/rating', ['value' => $value, 'outOf' => 5]))['value'])->toBe($shown);
})->with([
    'above the scale' => [7, 5],
    'a little over four' => [4.3, 4.5],
    'just over four' => [4.2, 4],
    'four and three quarters' => [4.75, 5],
    'nothing' => [0, 0],
]);

test('a rating says what it is in words', function () {
    expect(viewOf(block('r', 'core/rating', ['value' => 4.5, 'outOf' => 5]))['text'])->toBe('Rated 4.5 out of 5')
        ->and(viewOf(block('r', 'core/rating', ['value' => 4, 'outOf' => 5, 'label' => 'Service']))['text'])
        ->toBe('Service: rated 4 out of 5')
        ->and(viewOf(block('r', 'core/rating', ['value' => 3, 'outOf' => 10]))['outOf'])->toBe(10);
});
