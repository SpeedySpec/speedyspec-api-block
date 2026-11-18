<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\Ports;

/**
 * The WordPress functions that say where a post sits in the site, so the site map can be tested without WordPress.
 *
 * @since 0.1.0
 */
interface WpSiteInterface
{
    /**
     * @return string|null
     *   The post's address, or null when there is no such post.
     *
     * @since 0.1.0
     */
    public function permalink(int $id): ?string;

    /**
     * @since 0.1.0
     */
    public function title(int $id): string;

    /**
     * Lists a post's ancestors, the top one first and the nearest last.
     *
     * @return list<int>
     *
     * @since 0.1.0
     */
    public function ancestors(int $id): array;

    /**
     * @since 0.1.0
     */
    public function homeUrl(): string;

    /**
     * @since 0.1.0
     */
    public function siteName(): string;
}
