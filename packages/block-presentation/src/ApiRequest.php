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
     *   The raw request body: JSON, a file being uploaded, or empty.
     * @param array<string, string> $headers
     *   The request's headers; their names are kept in lower case, as HTTP treats them without regard to case.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly string $body = '',
        array $headers = [],
    )
    {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    /**
     * @var array<string, string>
     */
    public readonly array $headers;

    /**
     * Reads one header, whatever case its name is given in.
     *
     * @since 0.1.0
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
