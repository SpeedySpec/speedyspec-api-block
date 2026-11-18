<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Asks the gate steps whether a prepared block is shown, before any data source is called for it.
 *
 * @since 0.1.0
 */
class GateStage
{
    /**
     * Wires the stage to its steps.
     *
     * @param StepRegistryInterface $steps
     *   Supplies the gate steps.
     */
    public function __construct(private readonly StepRegistryInterface $steps)
    {
    }

    /**
     * Gates one block: a hidden block stops on the front end and is only marked in the admin, with no issue.
     *
     * @since 0.1.0
     */
    public function run(StageOutcome $prepared, BlockType $type, Context $context, Mode $mode): StageOutcome
    {
        if (!$prepared->continue)
        {
            return $prepared;
        }

        $block = $prepared->workingBlock();

        foreach ($this->steps->for(GateStepInterface::class) as $step)
        {
            if (!$step->shown($block, $type, $context, $mode))
            {
                return $mode === Mode::Admin
                    ? $prepared->with(hiddenOnFront: true)
                    : $prepared->with(continue: false);
            }
        }

        return $prepared;
    }
}
