<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One reason an edit was refused.
 *
 * @since 0.1.0
 */
class EditRefusal
{
    /**
     * Records the reason and, where there is one, the block and setting at fault.
     *
     * @param string $message
     *   Explains the refusal to a developer; a builder words its own message from the kind.
     * @param string|null $blockId
     *   The block at fault, by the id the caller knows it by: its id in the document, or for blocks being pasted
     *   their id before new ids were issued. Null for a block that does not exist yet.
     * @param string|null $path
     *   The setting at fault, as a setting path, when the refusal concerns one.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly EditRefusalKind $kind,
        public readonly string $message,
        public readonly ?string $blockId = null,
        public readonly ?string $path = null,
    )
    {
    }
}
