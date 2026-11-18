<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\EditResult;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusal;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Text\Domain\TextTypesPackage;

if (!function_exists('textContainer'))
{
    /**
     * Builds a container with the default implementations and the text definitions loaded.
     */
    function textContainer(): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);
        $container->get(BlockPackageLoaderInterface::class)->load(new TextTypesPackage());

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

if (!function_exists('doc'))
{
    /**
     * Builds a page document holding the given blocks.
     */
    function doc(Block ...$roots): Document
    {
        return new Document('d', 'page', new BlockTree(...$roots));
    }
}

if (!function_exists('resolved'))
{
    /**
     * Resolves one block on its own, for a visitor.
     */
    function resolved(Block $block): ResolvedBlock
    {
        return textContainer()->get(DocumentResolverInterface::class)->resolve(doc($block), Mode::Front)->find($block->id);
    }
}

if (!function_exists('refusals'))
{
    /**
     * Lists the kinds of refusal an edit came back with.
     */
    function refusals(EditResult $result): array
    {
        return array_map(fn (EditRefusal $refusal) => $refusal->kind, $result->refusals);
    }
}
