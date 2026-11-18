<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
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
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Structure\Domain\StructureTypesPackage;
use SpeedySpec\Block\Structure\StructurePackage;

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
     * Draws blocks as a page with only the defaults and the structure package loaded.
     *
     * @return array{0: string, 1: string, 2: list<\SpeedySpec\Block\Domain\ValueObjects\Issue>}
     *   The markup, the collected stylesheet, and the issues.
     */
    function page(Block ...$roots): array
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);
        $loader = $container->get(BlockPackageLoaderInterface::class);
        $loader->load(new StructureTypesPackage());
        $loader->load(StructurePackage::from($container));

        $result = $container->get(DocumentRendererInterface::class)
            ->render(new Document('d', 'page', new BlockTree(...$roots)), new RenderRequest(Mode::Front));

        return [$result->output, $container->get(StyleCollectorInterface::class)->css(), $result->issues];
    }
}

if (!function_exists('classOf'))
{
    /**
     * Finds the collected class on the nth element of some markup that has one.
     */
    function classOf(string $html, int $nth = 0): string
    {
        preg_match_all('/class="(b-[0-9a-f]{10})"/', $html, $found);

        return $found[1][$nth] ?? 'none';
    }
}
