<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Issue;

/**
 * What one resolving stage hands to the next, so each stage builds on the working values of the one before.
 *
 * Internal to the application layer.
 *
 * @since 0.1.0
 */
class StageOutcome
{
    /**
     * Holds one block's state between stages.
     *
     * @param Block $block
     *   The stored block with migrated settings and the type's current version; bindings and children as stored.
     * @param array<string, mixed> $settings
     *   The working settings.
     * @param array<string, array<string, mixed>> $extensions
     *   The working extension settings by decorator namespace.
     * @param list<Issue> $issues
     *   Every issue recorded so far, in the order the stages found them.
     * @param bool $continue
     *   Whether later stages should still process the block.
     * @param bool $hiddenOnFront
     *   Whether a gate step hid the block on the front end while the admin keeps it.
     * @param array<string, array{source: string, used: string}> $bindingInfo
     *   Maps a setting-path string to its data source and which candidate won.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly Block $block,
        public readonly array $settings,
        public readonly array $extensions,
        public readonly array $issues,
        public readonly bool $continue,
        public readonly bool $hiddenOnFront = false,
        public readonly array $bindingInfo = [],
    )
    {
    }

    /**
     * Copies the outcome changing only the named fields.
     *
     * @since 0.1.0
     */
    public function with(
        ?Block $block = null,
        ?array $settings = null,
        ?array $extensions = null,
        ?array $issues = null,
        ?bool $continue = null,
        ?bool $hiddenOnFront = null,
        ?array $bindingInfo = null,
    ): self
    {
        return new self(
            $block ?? $this->block,
            $settings ?? $this->settings,
            $extensions ?? $this->extensions,
            $issues ?? $this->issues,
            $continue ?? $this->continue,
            $hiddenOnFront ?? $this->hiddenOnFront,
            $bindingInfo ?? $this->bindingInfo,
        );
    }

    /**
     * Builds the block as steps should see it: the stored block carrying the working settings and extensions.
     *
     * @since 0.1.0
     */
    public function workingBlock(): Block
    {
        return new Block(
            $this->block->id,
            $this->block->type,
            $this->block->typeVersion,
            $this->settings,
            $this->extensions,
            $this->block->bindings,
            $this->block->children,
        );
    }
}
