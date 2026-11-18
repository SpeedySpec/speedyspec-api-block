<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\RenderResult;
use SpeedySpec\Block\Example\ExampleBlockPackage;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;

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
     * Draws one block as a page with only the default implementations and the example package loaded.
     */
    function rendered(Block $root, Mode $mode = Mode::Front): RenderResult
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);
        $container->get(BlockPackageLoaderInterface::class)->load(new ExampleBlockPackage());

        return $container->get(DocumentRendererInterface::class)
            ->render(new Document('d', 'page', new BlockTree($root)), new RenderRequest($mode));
    }
}

if (!function_exists('html'))
{
    /**
     * Draws one block and gives just the markup.
     */
    function html(Block $root, Mode $mode = Mode::Front): string
    {
        return rendered($root, $mode)->output;
    }
}
