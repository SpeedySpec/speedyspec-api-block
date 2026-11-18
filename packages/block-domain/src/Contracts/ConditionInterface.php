<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * One yes-or-no question a visibility rule can ask, such as "is the visitor signed in".
 *
 * Conditions are the only way a rule reaches outside the block system. A rule names a condition and gives it
 * arguments; there is no expression language to evaluate, so stored content can never run code.
 *
 * @since 0.1.0
 */
interface ConditionInterface
{
    /**
     * Names the condition as lower-case `vendor/name`.
     *
     * @since 0.1.0
     */
    public function name(): string;

    /**
     * Describes the arguments a rule may pass, so a builder can draw a form for them and bad ones are refused.
     *
     * @return array<string, mixed>
     *   A schema in the supported subset.
     *
     * @since 0.1.0
     */
    public function argumentsSchema(): array;

    /**
     * Answers the question for the block being resolved.
     *
     * @param array<string, mixed> $arguments
     *   Already checked against the arguments schema.
     * @param Context $context
     *   The context the block inherits, including the current item inside a repeating block.
     *
     * @since 0.1.0
     */
    public function holds(array $arguments, Context $context, Mode $mode): bool;
}
