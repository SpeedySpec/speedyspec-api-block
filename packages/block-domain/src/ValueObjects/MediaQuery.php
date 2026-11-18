<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * What a person is looking for in the media library, one page at a time.
 *
 * Paging is kept within bounds here, so no library can be asked for an unbounded list.
 *
 * @since 0.1.0
 */
class MediaQuery
{
    public const int MAX_PER_PAGE = 100;

    public readonly int $page;

    public readonly int $perPage;

    /**
     * @param string $search
     *   Text to find in item titles; empty for everything.
     * @param MediaKind|null $kind
     *   Only items of this kind; null for every kind.
     * @param int $page
     *   Counted from 1; anything lower is the first page.
     * @param int $perPage
     *   Kept between 1 and {@link MediaQuery::MAX_PER_PAGE}.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $search = '',
        public readonly ?MediaKind $kind = null,
        int $page = 1,
        int $perPage = 20,
    )
    {
        $this->page = max(1, $page);
        $this->perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
