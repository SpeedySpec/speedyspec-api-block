<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

/**
 * One answer from the builder API, for the host to send with its own framework.
 *
 * @since 0.1.0
 */
class ApiResponse
{
    /**
     * @param int $status
     *   The HTTP status.
     * @param array<string, mixed>|null $body
     *   What to send as JSON; null for an answer with no body.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly int $status,
        public readonly ?array $body = null,
    )
    {
    }

    /**
     * Encodes the body the way documents are stored, so a number or an empty object survives the trip.
     *
     * @return string
     *   JSON; empty when the answer has no body.
     *
     * @since 0.1.0
     */
    public function json(): string
    {
        if ($this->body === null)
        {
            return '';
        }

        return (string) json_encode(
            $this->body,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }
}
