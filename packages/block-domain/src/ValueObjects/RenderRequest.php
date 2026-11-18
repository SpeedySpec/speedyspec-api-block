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
     * @param bool $editing
     *   Whether the drawing is for an editor to work on, so outputs may mark what can be typed into. It only takes
     *   effect in admin mode: a visitor's page is never marked, whoever asks.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly Mode $mode = Mode::Front,
        public readonly string $target = 'html',
        bool $editing = false,
    )
    {
        $this->editing = $editing && $mode === Mode::Admin;
    }

    /**
     * Whether outputs should mark editable settings; true only for an admin drawing meant for an editor.
     *
     * @since 0.1.0
     */
    public readonly bool $editing;
}
