<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;
use SpeedySpec\Block\Infra\Memory\InMemorySiteMap;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Site\Domain\SiteTypesPackage;

if (!function_exists('siteMap'))
{
    /**
     * A site where the page `d` is at /about, two steps down, with search at /find as `q`.
     */
    function siteMap(): InMemorySiteMap
    {
        return new InMemorySiteMap(
            ['d' => 'https://e.test/about'],
            ['d' => [new Crumb('Home', '/'), new Crumb('Company', '/company'), new Crumb('About')]],
            new SearchTarget('/find', 'q'),
        );
    }
}

if (!function_exists('siteContainer'))
{
    /**
     * Builds a container with the defaults and the site definitions, with the sample site map or none.
     *
     * A stand-in heading type is added, as the text package defines, so the step has headings to find.
     */
    function siteContainer(?SiteMapInterface $map = null, bool $withMap = true): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);

        if ($withMap)
        {
            $container->add(SiteMapInterface::class, fn () => $map ?? siteMap());
        }

        $types = $container->get(\SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface::class);
        $types->add(BlockType::fromArray([
            'name' => 'core/heading',
            'title' => 'Heading',
            'settings' => ['type' => 'object', 'properties' => [
                'level' => ['type' => 'integer', 'default' => 2],
                'text' => ['type' => 'string', 'default' => ''],
                'anchor' => ['type' => 'string', 'default' => ''],
            ]],
            'uses' => ['core/heading-ids'],
        ]));
        $container->get(BlockPackageLoaderInterface::class)->load(SiteTypesPackage::from($container));

        return $container;
    }
}

if (!function_exists('block'))
{
    /**
     * Builds a block for tests.
     */
    function block(string $id, string $type, array $settings = [], Block ...$children): Block
    {
        return new Block($id, BlockName::fromString($type), 1, $settings, [], [], array_values($children));
    }
}

if (!function_exists('resolve'))
{
    /**
     * Resolves blocks as the page `d`, for a visitor.
     */
    function resolve(array $roots, bool $withMap = true): ResolvedDocument
    {
        return siteContainer(null, $withMap)->get(DocumentResolverInterface::class)
            ->resolve(new Document('d', 'page', new BlockTree(...$roots)), Mode::Front);
    }
}

if (!function_exists('viewOf'))
{
    /**
     * Resolves blocks and gives one block's view values, or null when it did not resolve.
     */
    function viewOf(string $id, array $roots, bool $withMap = true): ?array
    {
        return resolve($roots, $withMap)->find($id)?->view?->values;
    }
}

if (!function_exists('issuesOf'))
{
    /**
     * Lists one block's issues as kind, message and path.
     */
    function issuesOf(string $id, ResolvedDocument $resolved): array
    {
        return array_values(array_map(
            fn ($issue) => [$issue->kind, $issue->message, $issue->path],
            array_filter($resolved->issues, fn ($issue) => $issue->blockId === $id),
        ));
    }
}
