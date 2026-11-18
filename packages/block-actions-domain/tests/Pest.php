<?php

declare(strict_types=1);

use SpeedySpec\Block\Actions\Domain\ActionsTypesPackage;
use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;
use SpeedySpec\Block\Infra\Memory\InMemoryMediaLibrary;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;

if (!function_exists('actionsLibrary'))
{
    /**
     * Builds a library holding a portrait and a PDF.
     */
    function actionsLibrary(): InMemoryMediaLibrary
    {
        $library = new InMemoryMediaLibrary();
        $library->add(
            new MediaItem('face', 'https://e.test/face.jpg', MediaKind::Image, 'image/jpeg', 'Face', 'Ann', 96, 96),
        );
        $library->add(new MediaItem('doc', 'https://e.test/doc.pdf', MediaKind::File, 'application/pdf', 'Doc'));

        return $library;
    }
}

if (!function_exists('actionsContainer'))
{
    /**
     * Builds a container with the defaults and the actions definitions, with the sample library or with none.
     */
    function actionsContainer(bool $library = true): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);
        $container->remove(MediaLibraryInterface::class);

        if ($library)
        {
            $container->add(MediaLibraryInterface::class, fn () => actionsLibrary());
        }

        $container->get(BlockPackageLoaderInterface::class)->load(ActionsTypesPackage::from($container));

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
    function resolve(array $roots, bool $library = true): ResolvedDocument
    {
        return actionsContainer($library)->get(DocumentResolverInterface::class)
            ->resolve(new Document('d', 'page', new BlockTree(...$roots)), Mode::Front);
    }
}

if (!function_exists('viewOf'))
{
    /**
     * Resolves one block and gives its view's values, or null when it did not resolve.
     */
    function viewOf(Block $block, bool $library = true): ?array
    {
        return resolve([$block], $library)->find($block->id)?->view?->values;
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
