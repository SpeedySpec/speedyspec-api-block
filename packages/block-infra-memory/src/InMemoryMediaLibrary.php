<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaPage;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;

/**
 * Keeps media for the length of a request, for tests and hosts with no files of their own.
 *
 * @since 0.1.0
 */
class InMemoryMediaLibrary implements MediaLibraryInterface
{
    /**
     * @var list<MediaItem> Oldest first.
     */
    private array $items = [];

    private int $next = 1;

    /**
     * @param string $baseUrl
     *   Put before an item's id to make its URL; nothing is served there.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly string $baseUrl = 'memory://media/')
    {
    }

    /**
     * Puts an item in as it is, so a test can name its id, URL and sizes.
     *
     * @since 0.1.0
     */
    public function add(MediaItem $item): void
    {
        $this->items[] = $item;
    }

    public function find(string $id): ?MediaItem
    {
        foreach ($this->items as $item)
        {
            if ($item->id === $id)
            {
                return $item;
            }
        }

        return null;
    }

    public function search(MediaQuery $query): MediaPage
    {
        $matches = array_values(array_filter(
            array_reverse($this->items),
            static fn (MediaItem $item): bool => ($query->kind === null || $item->kind === $query->kind)
                && ($query->search === '' || mb_stripos($item->title, $query->search) !== false),
        ));

        return new MediaPage(
            array_slice($matches, ($query->page - 1) * $query->perPage, $query->perPage),
            count($matches),
        );
    }

    public function upload(MediaUpload $upload): MediaItem
    {
        $id = 'm' . $this->next++;
        $item = new MediaItem($id, $this->baseUrl . $id, $upload->kind(), $upload->mimeType, $upload->name);
        $this->items[] = $item;

        return $item;
    }
}
