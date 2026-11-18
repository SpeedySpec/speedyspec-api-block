<?php

declare(strict_types=1);

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
use SpeedySpec\Block\Domain\ValueObjects\MediaSize;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;
use SpeedySpec\Block\Infra\Memory\InMemoryMediaLibrary;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Media\Domain\MediaTypesPackage;

if (!function_exists('library'))
{
    /**
     * Builds a library holding an image with two sizes, a second image, a video, a sound and a PDF.
     */
    function library(): InMemoryMediaLibrary
    {
        $library = new InMemoryMediaLibrary();
        $sizes = [
            new MediaSize('large', 'https://e.test/photo-800.jpg', 800, 533),
            new MediaSize('thumb', 'https://e.test/photo-150.jpg', 150, 100),
        ];
        $library->add(new MediaItem(
            'photo',
            'https://e.test/photo.jpg',
            MediaKind::Image,
            'image/jpeg',
            'Photo',
            'A cat',
            1200,
            800,
            $sizes,
        ));
        $library->add(
            new MediaItem('other', 'https://e.test/other.png', MediaKind::Image, 'image/png', 'Other', '', 40, 30),
        );
        $library->add(new MediaItem('clip', 'https://e.test/clip.mp4', MediaKind::Video, 'video/mp4', 'Clip'));
        $library->add(new MediaItem('song', 'https://e.test/song.mp3', MediaKind::Audio, 'audio/mpeg', 'Song'));
        $library->add(new MediaItem('doc', 'https://e.test/doc.pdf', MediaKind::File, 'application/pdf', 'Doc'));

        return $library;
    }
}

if (!function_exists('mediaContainer'))
{
    /**
     * Builds a container with the default implementations, the sample library and the media definitions loaded.
     */
    function mediaContainer(): BlockServiceContainer
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);
        $container->remove(MediaLibraryInterface::class);
        $container->add(MediaLibraryInterface::class, fn () => library());
        $container->get(BlockPackageLoaderInterface::class)->load(MediaTypesPackage::from($container));

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
        return mediaContainer()->get(DocumentResolverInterface::class)
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
        return resolve($block)->find($block->id)?->view->values;
    }
}
