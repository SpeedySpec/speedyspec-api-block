<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaPage;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;

/**
 * The host's files, as media blocks and builders need them.
 *
 * Files belong to the host, so the block system only looks them up and hands uploads over; where and how they are
 * kept is the implementation's.
 *
 * @since 0.1.0
 */
interface MediaLibraryInterface
{
    /**
     * Looks up one item, for drawing a block that stores its id.
     *
     * @return MediaItem|null
     *   The item, or null when there is no item with that id.
     *
     * @since 0.1.0
     */
    public function find(string $id): ?MediaItem;

    /**
     * Lists the items a person may choose from, newest first, matching the search text in titles regardless of case.
     *
     * @since 0.1.0
     */
    public function search(MediaQuery $query): MediaPage;

    /**
     * Keeps an uploaded file and describes it.
     *
     * Two uploads with the same name are two items; neither replaces the other.
     *
     * @since 0.1.0
     */
    public function upload(MediaUpload $upload): MediaItem;
}
