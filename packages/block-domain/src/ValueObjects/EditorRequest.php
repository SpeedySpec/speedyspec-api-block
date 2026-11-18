<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * What a host tells an editor when it opens one, so the editor can reach the builder API for that document.
 *
 * @since 0.1.0
 */
class EditorRequest
{
    /**
     * @param string $apiUrl
     *   Where the host mounted the builder API.
     * @param array<string, string> $values
     *   Anything else the host needs sent with each request, such as a request-forgery token, by header name.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $documentId,
        public readonly string $apiUrl,
        public readonly array $values = [],
    )
    {
    }
}
