<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

/**
 * One request to the builder API, as plain values a host fills in from whatever framework it uses.
 *
 * @since 0.1.0
 */
class ApiRequest
{
    /**
     * @param string $method
     *   The HTTP method, in any case.
     * @param string $path
     *   The path below wherever the host mounts the API, such as `/documents/home`.
     * @param array<string, string> $query
     *   The query string's values.
     * @param string $body
     *   The raw request body; JSON, or empty.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly string $body = '',
    )
    {
    }
}
