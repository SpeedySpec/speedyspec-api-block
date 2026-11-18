<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;

/**
 * Where a page sits in the host's site: its address, the path to it, and where the site's search goes.
 *
 * Documents know nothing of the site around them, so blocks that point at it, such as breadcrumbs, a menu marking
 * the current page or a search form, ask the host through this.
 *
 * @since 0.1.0
 */
interface SiteMapInterface
{
    /**
     * Gives a page's address, for marking it current in a menu.
     *
     * @return string|null
     *   The address, or null when the host has none for it.
     *
     * @since 0.1.0
     */
    public function url(string $documentId): ?string;

    /**
     * Gives the path from the top of the site to a page, the page itself last.
     *
     * @return list<Crumb>
     *
     * @since 0.1.0
     */
    public function trail(string $documentId): array;

    /**
     * Says where the site's search form sends what was typed.
     *
     * @return SearchTarget|null
     *   Where to search, or null on a site with no search.
     *
     * @since 0.1.0
     */
    public function search(): ?SearchTarget;
}
