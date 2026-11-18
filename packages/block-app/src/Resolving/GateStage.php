<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use Throwable;

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
     * Gates one block: a hidden block stops on the front end and is only marked in the admin.
     *
     * Hiding records no issue; a step that throws records one and hides the block.
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
            try
            {
                $shown = $step->shown($block, $type, $context, $mode);
            }
            catch (Throwable $e)
            {
                // A step that cannot decide is treated as hiding the block, so a failure never reveals one.
                $issue = new Issue($block->id, IssueKind::StepFailed, sprintf(
                    'Gate step %s failed with %s.',
                    get_debug_type($step),
                    $e::class,
                ));

                return $this->hidden($prepared->with(issues: [...$prepared->issues, $issue]), $mode);
            }

            if (!$shown)
            {
                return $this->hidden($prepared, $mode);
            }
        }

        return $prepared;
    }

    /**
     * Stops a hidden block on the front end and only marks it in the admin, where the editor still shows it.
     */
    private function hidden(StageOutcome $outcome, Mode $mode): StageOutcome
    {
        return $mode === Mode::Admin
            ? $outcome->with(hiddenOnFront: true)
            : $outcome->with(continue: false);
    }
}
