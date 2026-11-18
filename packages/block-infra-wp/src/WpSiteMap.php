<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;
use SpeedySpec\Block\Infra\WP\Ports\WpSiteInterface;

/**
 * Says where a post sits in a WordPress site: its permalink, its parent pages, and WordPress's own search.
 *
 * Documents are posts, so their ids are post ids written as text.
 *
 * @since 0.1.0
 */
class WpSiteMap implements SiteMapInterface
{
    public function __construct(private readonly WpSiteInterface $site)
    {
    }

    public function url(string $documentId): ?string
    {
        $id = $this->postId($documentId);

        return $id === null ? null : $this->site->permalink($id);
    }

    public function trail(string $documentId): array
    {
        $id = $this->postId($documentId);

        if ($id === null || $this->site->permalink($id) === null)
        {
            return [];
        }

        $trail = [new Crumb($this->site->siteName(), $this->site->homeUrl())];

        foreach ($this->site->ancestors($id) as $ancestor)
        {
            $trail[] = new Crumb($this->site->title($ancestor), (string) $this->site->permalink($ancestor));
        }

        $trail[] = new Crumb($this->site->title($id));

        return $trail;
    }

    public function search(): ?SearchTarget
    {
        return new SearchTarget($this->site->homeUrl(), 's');
    }

    private function postId(string $documentId): ?int
    {
        return preg_match('/^[1-9][0-9]{0,18}$/', $documentId) === 1 ? (int) $documentId : null;
    }
}
