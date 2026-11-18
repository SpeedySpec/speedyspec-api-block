<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\Ports;

/**
 * The WordPress attachment functions the media library needs, so it can be tested without WordPress.
 *
 * @since 0.1.0
 */
interface WpAttachmentsInterface
{
    /**
     * Describes one attachment.
     *
     * @return array<string, mixed>|null
     *   `id`, `url`, `mimeType`, `title`, `alt`, `width` and `height` (null when unknown), and `sizes`, a list of
     *   `name`, `url`, `width` and `height`; or null when there is no such attachment.
     *
     * @since 0.1.0
     */
    public function attachment(int $id): ?array;

    /**
     * Lists attachment ids, newest first.
     *
     * @param string|null $mimePrefix
     *   Such as `image/`; null for every type.
     * @return array{ids: list<int>, total: int}
     *   One page of ids, and how many match across every page.
     *
     * @since 0.1.0
     */
    public function search(string $search, ?string $mimePrefix, int $page, int $perPage): array;

    /**
     * Adds an uploaded file to the media library.
     *
     * @return int
     *   The new attachment's id.
     *
     * @since 0.1.0
     */
    public function insert(string $name, string $mimeType, string $bytes): int;
}
