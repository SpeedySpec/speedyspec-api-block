<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaSize;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\RenderResult;
use SpeedySpec\Block\Infra\Memory\InMemoryMediaLibrary;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Media\Domain\MediaTypesPackage;
use SpeedySpec\Block\Media\MediaPackage;

if (!function_exists('mediaHost'))
{
    /**
     * Builds a container with the defaults and a library holding a sized image, a plain image, a video and a sound.
     */
    function mediaHost(): BlockServiceContainer
    {
        $library = new InMemoryMediaLibrary();
        $library->add(new MediaItem(
            'photo',
            'https://e.test/photo.jpg',
            MediaKind::Image,
            'image/jpeg',
            'Photo',
            'A "cat"',
            1200,
            800,
            [new MediaSize('thumb', 'https://e.test/photo-150.jpg', 150, 100)],
        ));
        $library->add(new MediaItem('plain', 'https://e.test/plain.png', MediaKind::Image, 'image/png', 'Plain'));
        $library->add(new MediaItem('clip', 'https://e.test/clip.mp4', MediaKind::Video, 'video/mp4', 'Clip'));
        $library->add(new MediaItem('song', 'https://e.test/song.mp3', MediaKind::Audio, 'audio/mpeg', 'Song'));

        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);
        $container->remove(MediaLibraryInterface::class);
        $container->add(MediaLibraryInterface::class, fn () => $library);

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
     * Draws blocks as a page with the media definitions and outputs loaded, and gives the result and its stylesheet.
     *
     * @return array{0: RenderResult, 1: string}
     */
    function rendered(array $blocks, Mode $mode = Mode::Front, bool $editing = false): array
    {
        $container = mediaHost();
        $loader = $container->get(BlockPackageLoaderInterface::class);
        $loader->load(PresentationPackage::from($container));
        $loader->load(MediaTypesPackage::from($container));
        $loader->load(MediaPackage::from($container));
        $result = $container->get(DocumentRendererInterface::class)
            ->render(new Document('d', 'page', new BlockTree(...$blocks)), new RenderRequest($mode, 'html', $editing));

        return [$result, $container->get(StyleCollectorInterface::class)->css()];
    }
}

if (!function_exists('page'))
{
    /**
     * Draws one block for a visitor, or while editing.
     */
    function page(Block $block, bool $editing = false): string
    {
        return rendered([$block], $editing ? Mode::Admin : Mode::Front, $editing)[0]->output;
    }
}
