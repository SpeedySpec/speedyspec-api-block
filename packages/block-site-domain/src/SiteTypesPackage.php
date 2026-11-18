<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site\Domain;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentStepInterface;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

/**
 * Defines the blocks that tie a page to the site around it: menus, a table of contents, breadcrumbs and search, and
 * the post list template.
 *
 * Its page step sees the whole page before any block is drawn; that is how a table of contents knows the page's
 * headings and how every heading gets a unique id. A site map is optional: without one, breadcrumbs and search are
 * not set up and no menu link is marked current. The settings are fixed; changing one needs a migration.
 *
 * @since 0.1.0
 */
class SiteTypesPackage implements BlockPackageInterface
{
    /**
     * @param SiteMapInterface|null $siteMap
     *   Where the host says pages sit; null on a host that says nothing.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly ?SiteMapInterface $siteMap = null)
    {
    }

    /**
     * Builds the package with the container's site map, when it has one.
     *
     * @since 0.1.0
     */
    public static function from(BlockServiceContainer $container): self
    {
        return new self($container->has(SiteMapInterface::class) ? $container->get(SiteMapInterface::class) : null);
    }

    public function name(): string
    {
        return 'core/site-types';
    }

    public function title(): string
    {
        return 'Site';
    }

    public function description(): string
    {
        return 'Menus, a table of contents, breadcrumbs, search and a list of recent posts.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $type = fn (array $definition) => $registrar->type(
            BlockType::fromArray(['category' => 'site', ...$definition]),
        );
        $behaviour = fn (string $name, BlockBehaviourInterface $behaviour) => $registrar->behaviour(
            BlockName::fromString($name),
            $behaviour,
        );
        $text = fn (string $default, int $max, string $title): array => [
            'type' => 'string',
            'default' => $default,
            'maxLength' => $max,
            'title' => $title,
        ];
        $level = fn (int $default, string $title): array => [
            'type' => 'integer',
            'default' => $default,
            'minimum' => 1,
            'maximum' => 6,
            'title' => $title,
        ];

        $registrar->step(DocumentStepInterface::class, new SitePageStep($this->siteMap));
        $registrar->template(PostListTemplate::template());

        $type([
            'name' => 'core/navigation',
            'title' => 'Menu',
            'description' => 'Links to the site\'s pages, with submenus.',
            'keywords' => ['navigation', 'nav', 'links'],
            'settings' => ['type' => 'object', 'properties' => [
                'label' => [...$text('Main', 80, 'Name'), 'description' => 'What the menu is, for screen readers.'],
                'orientation' => [
                    'enum' => ['horizontal', 'vertical'],
                    'default' => 'horizontal',
                    'title' => 'Direction',
                ],
            ]],
            'children' => ['core/navigation-link'],
            'examples' => [['label' => 'Main']],
        ]);

        $type([
            'name' => 'core/navigation-link',
            'title' => 'Menu link',
            'description' => 'One link in a menu, which may open a submenu of its own.',
            'settings' => ['type' => 'object', 'properties' => [
                'label' => $text('Link', 80, 'Text'),
                'url' => [...$text('', 2000, 'Link to'), 'description' => 'A page on this site or a web address.'],
                'newTab' => ['type' => 'boolean', 'default' => false, 'title' => 'Open in a new tab'],
            ]],
            'parents' => ['core/navigation', 'core/navigation-link'],
            'children' => ['core/navigation-link'],
            'uses' => [SitePageStep::PAGE_URL, NavigationLinkBehaviour::DEPTH],
            'provides' => [NavigationLinkBehaviour::DEPTH => 'depth'],
            'examples' => [['label' => 'About', 'url' => '/about']],
        ]);
        $behaviour('core/navigation-link', new NavigationLinkBehaviour());

        $type([
            'name' => 'core/table-of-contents',
            'title' => 'Table of contents',
            'description' => 'Links to the page\'s headings.',
            'keywords' => ['toc', 'contents', 'jump links'],
            'settings' => ['type' => 'object', 'properties' => [
                'title' => $text('On this page', 80, 'Title'),
                'from' => $level(2, 'From heading level'),
                'to' => $level(3, 'To heading level'),
            ]],
            'uses' => [SitePageStep::HEADINGS],
            'examples' => [['from' => 2, 'to' => 3]],
        ]);
        $behaviour('core/table-of-contents', new TableOfContentsBehaviour());

        $type([
            'name' => 'core/breadcrumbs',
            'title' => 'Breadcrumbs',
            'description' => 'The path from the top of the site to this page.',
            'keywords' => ['trail', 'path'],
            'settings' => ['type' => 'object', 'properties' => [
                'label' => [...$text('Breadcrumb', 80, 'Name'), 'description' => 'What it is, for screen readers.'],
            ]],
            'uses' => [SitePageStep::BREADCRUMBS],
            'examples' => [['label' => 'Breadcrumb']],
        ]);
        $behaviour('core/breadcrumbs', new BreadcrumbsBehaviour());

        $type([
            'name' => 'core/search',
            'title' => 'Search',
            'description' => 'A box for searching the site.',
            'keywords' => ['find', 'form'],
            'settings' => ['type' => 'object', 'properties' => [
                'label' => $text('Search', 80, 'Label'),
                'placeholder' => $text('', 80, 'Hint inside the box'),
                'button' => $text('Search', 40, 'Button text'),
            ]],
            'uses' => [SitePageStep::SEARCH],
            'examples' => [['label' => 'Search']],
        ]);
        $behaviour('core/search', new SearchBehaviour());
    }
}
