<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Says how a document should be drawn: for whom, and for which output target.
 *
 * @since 0.1.0
 */
class RenderRequest
{
    /**
     * Defaults to what a visitor sees, as HTML, so the safe choice needs no arguments.
     *
     * @param string $target
     *   The output target, for example `html`.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly Mode $mode = Mode::Front,
        public readonly string $target = 'html',
    )
    {
    }
}
