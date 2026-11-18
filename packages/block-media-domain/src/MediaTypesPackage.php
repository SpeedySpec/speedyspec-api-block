<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;

/**
 * Defines the blocks that show files and other sites' content: images, galleries, video, audio, embeds, icons and
 * maps.
 *
 * Blocks store media ids, never URLs, and their behaviours look the files up in the host's library when the page is
 * drawn, so moving files does not break pages. How the blocks are drawn is another package's. The settings are
 * fixed; changing one needs a migration.
 *
 * @since 0.1.0
 */
class MediaTypesPackage implements BlockPackageInterface
{
    /**
     * An icon's name; which names an outputs package can draw is that package's.
     */
    public const string ICON_NAME = '^[a-z0-9-]{1,40}$';

    public function __construct(private readonly MediaLibraryInterface $library)
    {
    }

    /**
     * Builds the package around the media library bound in a container.
     *
     * @since 0.1.0
     */
    public static function from(BlockServiceContainer $container): self
    {
        return new self($container->get(MediaLibraryInterface::class));
    }

    public function name(): string
    {
        return 'core/media-types';
    }

    public function title(): string
    {
        return 'Media';
    }

    public function description(): string
    {
        return 'Images, galleries, video, audio, embedded videos, icons and maps.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $lookup = new MediaLookup($this->library);
        $type = fn (array $definition) => $registrar->type(
            BlockType::fromArray(['category' => 'media', ...$definition]),
        );
        $behaviour = fn (string $name, BlockBehaviourInterface $behaviour) => $registrar->behaviour(
            BlockName::fromString($name),
            $behaviour,
        );
        $media = fn (MediaKind $kind, string $title): array => [
            'type' => 'string',
            'default' => '',
            'x-control' => 'media',
            'x-media-kind' => $kind->value,
            'title' => $title,
        ];
        $rich = fn (string $title): array => [
            'type' => 'string',
            'default' => '',
            'x-control' => 'rich-text',
            'title' => $title,
        ];
        $flag = fn (bool $default, string $title): array => [
            'type' => 'boolean',
            'default' => $default,
            'title' => $title,
        ];
        $icon = fn (string $default, string $description): array => [
            'type' => 'string',
            'default' => $default,
            'pattern' => $default === '' ? '^([a-z0-9-]{1,40})?$' : self::ICON_NAME,
            'title' => 'Icon',
            'description' => $description,
        ];

        $type([
            'name' => 'core/image',
            'title' => 'Image',
            'description' => 'A picture from the media library.',
            'keywords' => ['picture', 'photo', 'img'],
            'settings' => ['type' => 'object', 'properties' => [
                'media' => $media(MediaKind::Image, 'Image'),
                'alt' => [
                    'type' => ['string', 'null'],
                    'default' => null,
                    'maxLength' => 500,
                    'title' => 'Alternative text',
                    'description' => 'What the image shows, for people who cannot see it. Leave unset to use the '
                        . 'library\'s; empty for a decorative image.',
                ],
                'caption' => $rich('Caption'),
                'link' => ['type' => 'string', 'default' => '', 'maxLength' => 2000, 'title' => 'Link to'],
                'size' => [
                    'type' => 'string',
                    'default' => '',
                    'pattern' => '^[a-z0-9_-]{0,32}$',
                    'title' => 'Size',
                    'description' => 'A size the site makes, such as large. Empty for the whole image.',
                ],
            ]],
            'examples' => [['media' => '', 'caption' => 'A caption']],
        ]);
        $behaviour('core/image', new ImageBehaviour($lookup));

        $type([
            'name' => 'core/gallery',
            'title' => 'Gallery',
            'description' => 'Several images in a grid.',
            'keywords' => ['images', 'photos', 'grid'],
            'settings' => ['type' => 'object', 'properties' => [
                'media' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'default' => [],
                    'maxItems' => 100,
                    'x-control' => 'media-list',
                    'x-media-kind' => MediaKind::Image->value,
                    'title' => 'Images',
                ],
                'columns' => [
                    'type' => ['integer', 'object'],
                    'default' => 3,
                    'minimum' => 1,
                    'maximum' => 6,
                    'additionalProperties' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6],
                    'x-control' => 'responsive',
                    'title' => 'Columns',
                ],
                'caption' => $rich('Caption'),
            ]],
            'examples' => [['media' => [], 'columns' => 3]],
        ]);
        $behaviour('core/gallery', new GalleryBehaviour($lookup));

        foreach ([MediaKind::Video, MediaKind::Audio] as $kind)
        {
            $video = $kind === MediaKind::Video;
            $type([
                'name' => 'core/' . $kind->value,
                'title' => $video ? 'Video' : 'Audio',
                'description' => $video ? 'A video from the media library.' : 'A sound file from the media library.',
                'keywords' => $video ? ['movie', 'clip', 'mp4'] : ['sound', 'music', 'podcast', 'mp3'],
                'settings' => ['type' => 'object', 'properties' => [
                    'media' => $media($kind, $video ? 'Video' : 'Audio'),
                    ...($video ? ['poster' => $media(MediaKind::Image, 'Poster image')] : []),
                    'controls' => $flag(true, 'Show controls'),
                    'autoplay' => [
                        ...$flag(false, 'Play by itself'),
                        'description' => 'It plays muted, as browsers require.',
                    ],
                    'loop' => $flag(false, 'Play again at the end'),
                    'caption' => $rich('Caption'),
                ]],
                'examples' => [['media' => '', 'controls' => true]],
            ]);
            $behaviour('core/' . $kind->value, new PlayerBehaviour($lookup, $kind));
        }

        $type([
            'name' => 'core/embed',
            'title' => 'Embed',
            'description' => 'A video from YouTube or Vimeo.',
            'keywords' => ['youtube', 'vimeo', 'video', 'iframe'],
            'settings' => ['type' => 'object', 'properties' => [
                'url' => [
                    'type' => 'string',
                    'default' => '',
                    'pattern' => EmbedProviders::PATTERN,
                    'title' => 'Link',
                    'description' => 'A YouTube or Vimeo link, such as https://youtu.be/…',
                ],
                'title' => [
                    'type' => 'string',
                    'default' => 'Embedded content',
                    'maxLength' => 200,
                    'title' => 'Title',
                    'description' => 'What it is, for people using screen readers.',
                ],
                'caption' => $rich('Caption'),
            ]],
            'examples' => [['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'A video']],
        ]);
        $behaviour('core/embed', new EmbedBehaviour());

        $type([
            'name' => 'core/icon',
            'title' => 'Icon',
            'description' => 'A small symbol.',
            'keywords' => ['symbol', 'glyph'],
            'settings' => ['type' => 'object', 'properties' => [
                'name' => $icon('star', 'Such as star, check, heart or arrow-right.'),
                'label' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 200,
                    'title' => 'Label',
                    'description' => 'What it means, for people who cannot see it. Empty when it is only decoration.',
                ],
            ]],
            'examples' => [['name' => 'star', 'label' => '']],
        ]);

        $type([
            'name' => 'core/icon-list',
            'title' => 'Icon list',
            'description' => 'A list with an icon before each item.',
            'keywords' => ['features', 'checklist', 'bullets'],
            'settings' => ['type' => 'object', 'properties' => [
                'icon' => $icon('check', 'The icon each item shows unless it has its own.'),
            ]],
            'children' => ['core/icon-list-item'],
            'provides' => ['core/icon' => 'icon'],
            'examples' => [['icon' => 'check']],
        ]);

        $type([
            'name' => 'core/icon-list-item',
            'title' => 'Icon list item',
            'description' => 'One entry in an icon list.',
            'settings' => ['type' => 'object', 'properties' => [
                'icon' => $icon('', 'Empty to use the list\'s icon.'),
                'text' => $rich('Text'),
            ]],
            'parents' => ['core/icon-list'],
            'uses' => ['core/icon'],
            'examples' => [['text' => 'Free delivery']],
        ]);
        $behaviour('core/icon-list-item', new IconListItemBehaviour());

        $type([
            'name' => 'core/map',
            'title' => 'Map',
            'description' => 'A map of a place, from OpenStreetMap.',
            'keywords' => ['location', 'address', 'directions'],
            'settings' => ['type' => 'object', 'properties' => [
                'address' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 200,
                    'title' => 'Address',
                    'description' => 'Shown as a link to the map. Add coordinates to show the map itself.',
                ],
                'latitude' => [
                    'type' => ['number', 'null'],
                    'default' => null,
                    'minimum' => -90,
                    'maximum' => 90,
                    'title' => 'Latitude',
                ],
                'longitude' => [
                    'type' => ['number', 'null'],
                    'default' => null,
                    'minimum' => -180,
                    'maximum' => 180,
                    'title' => 'Longitude',
                ],
                'zoom' => [
                    'type' => 'integer',
                    'default' => 15,
                    'minimum' => 1,
                    'maximum' => 19,
                    'title' => 'Zoom',
                    'description' => 'From 1, the whole world, to 19, a single street.',
                ],
                'title' => ['type' => 'string', 'default' => 'Map', 'maxLength' => 200, 'title' => 'Title'],
            ]],
            'examples' => [['latitude' => 51.5074, 'longitude' => -0.1278, 'zoom' => 13, 'title' => 'London']],
        ]);
        $behaviour('core/map', new MapBehaviour());
    }
}
