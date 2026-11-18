<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\RenderResult;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Text\Domain\TextTypesPackage;
use SpeedySpec\Block\Text\TextPackage;

if (!function_exists('textHost'))
{
    /**
     * Builds a container with the default implementations, for the outputs package to be built from.
     */
    function textHost(): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);

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

if (!function_exists('rendered'))
{
    /**
     * Draws blocks as a page with the style settings and both text packages loaded.
     *
     * @return array{0: RenderResult, 1: string}
     *   The result and the collected stylesheet.
     */
    function rendered(array $blocks, bool $editing = false): array
    {
        $container = textHost();
        $loader = $container->get(BlockPackageLoaderInterface::class);
        $loader->load(PresentationPackage::from($container));
        $loader->load(new TextTypesPackage());
        $loader->load(TextPackage::from($container));
        $request = new RenderRequest($editing ? Mode::Admin : Mode::Front, 'html', $editing);
        $result = $container->get(DocumentRendererInterface::class)
            ->render(new Document('d', 'page', new BlockTree(...$blocks)), $request);

        return [$result, $container->get(StyleCollectorInterface::class)->css()];
    }
}

if (!function_exists('page'))
{
    /**
     * Draws one block and gives just its markup.
     */
    function page(Block $block, bool $editing = false): string
    {
        return rendered([$block], $editing)[0]->output;
    }
}
