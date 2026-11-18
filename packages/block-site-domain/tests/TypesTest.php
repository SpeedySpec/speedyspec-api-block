<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

function siteType(string $name): BlockType
{
    $type = siteContainer()->get(BlockTypeRegistryInterface::class)->get(BlockName::fromString($name));

    expect($type)->not->toBeNull();

    return $type;
}

test('every site block is in the site category with a title, description and example', function () {
    $names = ['core/navigation', 'core/navigation-link', 'core/table-of-contents', 'core/breadcrumbs', 'core/search'];

    foreach ($names as $name)
    {
        $type = siteType($name);

        expect($type->catalogue->category)->toBe('site')
            ->and($type->catalogue->title)->not->toBe('')
            ->and($type->catalogue->description)->not->toBe('')
            ->and($type->examples)->not->toBe([]);
    }
});

test('menus hold links, links hold links, and each block reads what the page step gives', function () {
    $link = BlockName::fromString('core/navigation-link');

    expect(siteType('core/navigation')->children->allows($link))->toBeTrue()
        ->and(siteType('core/navigation')->children->allows(BlockName::fromString('core/search')))->toBeFalse()
        ->and(siteType('core/navigation-link')->children->allows($link))->toBeTrue()
        ->and(siteType('core/navigation-link')->parents->allows(BlockName::fromString('core/navigation')))->toBeTrue()
        ->and(siteType('core/navigation-link')->parents->allows($link))->toBeTrue()
        ->and(siteType('core/navigation-link')->parents->allows(null))->toBeFalse()
        ->and(siteType('core/navigation-link')->uses)->toBe(['core/page-url', 'core/nav-depth'])
        ->and(siteType('core/navigation-link')->provides)->toBe(['core/nav-depth' => 'depth'])
        ->and(siteType('core/table-of-contents')->uses)->toBe(['core/headings'])
        ->and(siteType('core/breadcrumbs')->uses)->toBe(['core/breadcrumbs'])
        ->and(siteType('core/search')->uses)->toBe(['core/search']);
});

test('defaults are fixed', function () {
    $defaults = fn (string $name): array => array_map(
        fn (array $property) => $property['default'] ?? null,
        siteType($name)->settingsSchema['properties'],
    );

    expect($defaults('core/navigation'))->toBe(['label' => 'Main', 'orientation' => 'horizontal'])
        ->and($defaults('core/navigation-link'))->toBe(['label' => 'Link', 'url' => '', 'newTab' => false])
        ->and($defaults('core/table-of-contents'))->toBe(['title' => 'On this page', 'from' => 2, 'to' => 3])
        ->and($defaults('core/breadcrumbs'))->toBe(['label' => 'Breadcrumb'])
        ->and($defaults('core/search'))->toBe(['label' => 'Search', 'placeholder' => '', 'button' => 'Search']);
});

test('the post list is a template repeating over recent posts, with children bound to each post', function () {
    $template = siteContainer()->get(BlockTemplateRegistryInterface::class)->find('core/post-list');
    $repeat = $template?->tree->roots()[0];
    $section = $repeat?->children[0];
    [$heading, $paragraph, $button] = $section?->children ?? [null, null, null];

    expect($template?->title)->toBe('Post list')
        ->and($template?->category)->toBe('site')
        ->and($repeat?->type->toString())->toBe('core/repeat')
        ->and($repeat?->settings)->toBe(['source' => 'core/recent-posts', 'limit' => 5])
        ->and($section?->type->toString())->toBe('core/section')
        ->and($heading?->type->toString())->toBe('core/heading')
        ->and($heading?->settings['level'] ?? null)->toBe(3)
        ->and($heading?->bindings['text']->toArray())
        ->toBe(['source' => 'core/current-item', 'arguments' => ['field' => 'title'], 'fallback' => 'Post title'])
        ->and($paragraph?->bindings['text']->toArray()['arguments'])->toBe(['field' => 'excerpt'])
        ->and($button?->settings['text'] ?? null)->toBe('Read more')
        ->and($button?->bindings['link']->toArray()['arguments'])->toBe(['field' => 'url']);
});
