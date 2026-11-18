<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;
use SpeedySpec\Block\Infra\Memory\InMemorySiteMap;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Site\Domain\SiteTypesPackage;
use SpeedySpec\Block\Site\SitePackage;
use SpeedySpec\Block\Text\Domain\TextTypesPackage;
use SpeedySpec\Block\Text\TextPackage;

if (!function_exists('siteHost'))
{
    /**
     * Builds a container with the defaults and a site map placing the page `d` at /about, or with none.
     */
    function siteHost(?SiteMapInterface $map = null, bool $withMap = true): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);

        if ($withMap)
        {
            $container->add(SiteMapInterface::class, fn () => $map ?? new InMemorySiteMap(
                ['d' => '/about'],
                ['d' => [new Crumb('Home', '/'), new Crumb('<b>Co</b>', '/co'), new Crumb('About', '/about')]],
                new SearchTarget('/find', 'q'),
            ));
        }

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

if (!function_exists('page'))
{
    /**
     * Draws blocks as the page `d`, with the text and site packages loaded, for a visitor or while editing.
     */
    function page(array $blocks, bool $editing = false, ?SiteMapInterface $map = null, bool $withMap = true): string
    {
        $container = siteHost($map, $withMap);
        $loader = $container->get(BlockPackageLoaderInterface::class);
        $loader->load(PresentationPackage::from($container));
        $loader->load(new TextTypesPackage());
        $loader->load(TextPackage::from($container));
        $loader->load(SiteTypesPackage::from($container));
        $loader->load(new SitePackage());
        $request = new RenderRequest($editing ? Mode::Admin : Mode::Front, 'html', $editing);

        return $container->get(DocumentRendererInterface::class)
            ->render(new Document('d', 'page', new BlockTree(...$blocks)), $request)
            ->output;
    }
}
