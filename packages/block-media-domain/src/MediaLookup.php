<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Exceptions\UnavailableBlockException;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;

/**
 * Finds the media a block shows, so every media block says the same thing when it cannot.
 *
 * @since 0.1.0
 */
class MediaLookup
{
    private const array NOUNS = [
        'image' => ['an image', 'image'],
        'video' => ['a video', 'video'],
        'audio' => ['an audio file', 'audio file'],
        'file' => ['a file', 'file'],
    ];

    public function __construct(private readonly MediaLibraryInterface $library)
    {
    }

    /**
     * Finds the item a block was given, or says why the block cannot be drawn.
     *
     * A block with nothing chosen yet is not set up rather than broken, so it raises no issue: a new block would
     * otherwise be reported the moment it is added.
     *
     * @return MediaItem|null
     *   The item, or null when no id has been chosen.
     *
     * @throws UnavailableBlockException
     *   When the chosen item no longer exists, or is of another kind.
     *
     * @since 0.1.0
     */
    public function chosen(mixed $id, MediaKind $kind, string $path = 'media'): ?MediaItem
    {
        [$article, $noun] = self::NOUNS[$kind->value];

        if (!is_string($id) || $id === '')
        {
            return null;
        }

        $item = $this->library->find($id);

        if ($item === null)
        {
            throw new UnavailableBlockException(
                IssueKind::UnavailableMedia,
                "The chosen {$noun} no longer exists.",
                $path,
            );
        }

        if ($item->kind !== $kind)
        {
            throw new UnavailableBlockException(
                IssueKind::UnavailableMedia,
                "The chosen file is not {$article}.",
                $path,
            );
        }

        return $item;
    }

    /**
     * Finds an item of a kind when there is one, for media a block can do without, such as a poster.
     *
     * @since 0.1.0
     */
    public function optional(mixed $id, MediaKind $kind): ?MediaItem
    {
        $item = is_string($id) && $id !== '' ? $this->library->find($id) : null;

        return $item !== null && $item->kind === $kind ? $item : null;
    }
}
