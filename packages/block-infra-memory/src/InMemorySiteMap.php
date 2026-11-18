<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;

/**
 * Says where pages sit from lists given to it, for tests and for hosts whose site is small enough to write down.
 *
 * @since 0.1.0
 */
class InMemorySiteMap implements SiteMapInterface
{
    /**
     * @param array<string, string> $urls
     *   Each page's address, by document id.
     * @param array<string, list<Crumb>> $trails
     *   Each page's trail, by document id, the page itself last.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly array $urls = [],
        private readonly array $trails = [],
        private readonly ?SearchTarget $search = null,
    )
    {
    }

    public function url(string $documentId): ?string
    {
        return $this->urls[$documentId] ?? null;
    }

    public function trail(string $documentId): array
    {
        return $this->trails[$documentId] ?? [];
    }

    public function search(): ?SearchTarget
    {
        return $this->search;
    }
}
