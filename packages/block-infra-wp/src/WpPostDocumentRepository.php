<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException;
use SpeedySpec\Block\Infra\WP\Ports\WpPostsInterface;

/**
 * Treats each WordPress post as a document: its content is the blocks, its post type the kind.
 *
 * @since 0.1.0
 */
class WpPostDocumentRepository implements DocumentRepositoryInterface
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly WpPostsInterface $posts,
        private readonly WpMarkup $markup,
    )
    {
    }

    public function find(string $id): ?Document
    {
        $content = $this->posts->content($id);

        return $content === null ? null : new Document($id, $this->posts->kind($id), $this->markup->parse($content));
    }

    public function ids(): array
    {
        return $this->posts->ids();
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidDocumentException
     *   When the document is in a format block markup cannot hold.
     *
     * @since 0.1.0
     */
    public function save(Document $document): void
    {
        if ($document->formatVersion !== 1)
        {
            throw new InvalidDocumentException(sprintf(
                'A post cannot hold document format %d.',
                $document->formatVersion,
            ));
        }

        $this->posts->save($document->id, $document->kind, $this->markup->serialize($document->tree));
    }

    public function remove(string $id): void
    {
        $this->posts->delete($id);
    }
}
