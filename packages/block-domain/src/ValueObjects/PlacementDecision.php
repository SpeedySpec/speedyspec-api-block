<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The answer to "may this block go here?", with the reason when the answer is no.
 *
 * @since 0.1.0
 */
class PlacementDecision
{
    /**
     * Keeps the constructor private so a refusal always has a reason and an approval never does.
     *
     * @since 0.1.0
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly ?PlacementRefusal $reason,
        public readonly ?string $message,
    )
    {
    }

    /**
     * Says the block may be placed.
     *
     * @since 0.1.0
     */
    public static function allowed(): self
    {
        return new self(true, null, null);
    }

    /**
     * Says the block may not be placed, and why.
     *
     * @param string $message
     *   A sentence a builder can show to the person editing.
     *
     * @since 0.1.0
     */
    public static function refused(PlacementRefusal $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }
}
