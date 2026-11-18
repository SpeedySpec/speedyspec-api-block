<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an upload breaks the host's rules, with the HTTP status a builder API answers with.
 *
 * It lives with the contracts because any host that accepts uploads, not only the builder API, catches it.
 *
 * @since 0.1.0
 */
class MediaUploadRefusedException extends RuntimeException
{
    /**
     * @param int $status
     *   400 for a request that is missing something, 413 for a file over the limit, 415 for a kind not accepted.
     * @param string $message
     *   What the person uploading should know.
     *
     * @since 0.1.0
     */
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
