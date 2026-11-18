<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
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
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Interactive\Domain\InteractiveTypesPackage;
use SpeedySpec\Block\Interactive\InteractivePackage;

if (!function_exists('interactiveHost'))
{
    /**
     * Builds a container with the defaults.
     */
    function interactiveHost(): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);

        return $container;
    }
}

if (!function_exists('stoppedClock'))
{
    /**
     * Gives a clock stopped at noon on 18 November 2026, in UTC.
     */
    function stoppedClock(): Closure
    {
        return static fn (): DateTimeImmutable => new DateTimeImmutable('2026-11-18T12:00:00Z');
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
     * Draws blocks as a page with the interactive definitions and outputs loaded.
     */
    function rendered(array $blocks, Mode $mode = Mode::Front, bool $editing = false): RenderResult
    {
        $container = interactiveHost();
        $loader = $container->get(BlockPackageLoaderInterface::class);
        $loader->load(PresentationPackage::from($container));
        $loader->load(new InteractiveTypesPackage(stoppedClock()));
        $loader->load(new InteractivePackage());

        return $container->get(DocumentRendererInterface::class)
            ->render(new Document('d', 'page', new BlockTree(...$blocks)), new RenderRequest($mode, 'html', $editing));
    }
}

if (!function_exists('page'))
{
    /**
     * Draws one block for a visitor, or while editing.
     */
    function page(Block $block, bool $editing = false): string
    {
        return rendered([$block], $editing ? Mode::Admin : Mode::Front, $editing)->output;
    }
}

if (!function_exists('para'))
{
    /**
     * A child block for the containers to hold: the interactive package defines none of its own content.
     */
    function para(string $id): Block
    {
        return block($id, 'core/counter', ['to' => 5]);
    }
}
