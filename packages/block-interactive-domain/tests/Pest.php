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
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Interactive\Domain\InteractiveTypesPackage;

if (!function_exists('fixedClock'))
{
    /**
     * Gives a clock stopped at noon on 18 November 2026, in UTC.
     */
    function fixedClock(): Closure
    {
        return static fn (): DateTimeImmutable => new DateTimeImmutable('2026-11-18T12:00:00Z');
    }
}

if (!function_exists('interactiveContainer'))
{
    /**
     * Builds a container with the defaults and the interactive definitions, on the fixed clock.
     */
    function interactiveContainer(): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);
        $container->get(BlockPackageLoaderInterface::class)->load(new InteractiveTypesPackage(fixedClock()));

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
     * Resolves blocks as one page, for a visitor.
     */
    function resolve(Block ...$roots): ResolvedDocument
    {
        return interactiveContainer()->get(DocumentResolverInterface::class)
            ->resolve(new Document('d', 'page', new BlockTree(...$roots)), Mode::Front);
    }
}

if (!function_exists('viewOf'))
{
    /**
     * Resolves one block and gives its view's values, or null when it did not resolve.
     */
    function viewOf(Block $block): ?array
    {
        return resolve($block)->find($block->id)?->view?->values;
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
