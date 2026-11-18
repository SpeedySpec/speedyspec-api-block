<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;

/**
 * Thrown by a behaviour when its block cannot be drawn, such as when the media it shows no longer exists.
 *
 * The block is then treated as unresolved: visitors get nothing, a builder gets its placeholder, and the reason is
 * reported as an issue of the given kind rather than as a failed behaviour.
 *
 * @since 0.1.0
 */
class UnavailableBlockException extends RuntimeException
{
    /**
     * @param string $message
     *   What a person editing the page should do about it.
     * @param string|null $path
     *   The setting at fault, for builders to show the message beside it.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly IssueKind $kind,
        string $message,
        public readonly ?string $path = null,
    )
    {
        parent::__construct($message);
    }
}
