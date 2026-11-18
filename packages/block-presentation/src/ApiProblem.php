<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

use RuntimeException;

/**
 * Ends a request with a status and a message that is safe to show the person who made it.
 *
 * @since 0.1.0
 */
class ApiProblem extends RuntimeException
{
    /**
     * @since 0.1.0
     */
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
