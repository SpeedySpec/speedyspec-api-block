<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Adds checks a schema cannot express, such as rules spanning several settings.
 *
 * Runs only after the settings passed their schema; the issues it reports are shown but do not stop the block.
 *
 * @since 0.1.0
 */
interface ValidateStepInterface
{
    /**
     * Reports the problems found in the resolved settings.
     *
     * @param Block $block
     *   The block carrying its resolved settings and extensions.
     * @param BlockType $type
     *   The block's registered type.
     * @param array<string, mixed> $settings
     *   The resolved settings.
     * @param Context $context
     *   The context inherited from ancestors.
     * @param Mode $mode
     *   Where the block is resolved.
     *
     * @return list<Issue>
     *
     * @since 0.1.0
     */
    public function validate(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): array;
}
