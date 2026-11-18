<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ViewStepInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\CurrentItem;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use Throwable;

/**
 * Builds a validated block's view: plain values with no markup, ready for any renderer or form handler.
 *
 * @since 0.1.0
 */
class ViewStage
{
    /**
     * Wires the stage to what it reads.
     *
     * @param BlockBehaviourRegistryInterface $behaviours
     *   Finds the behaviour that computes a type's view.
     * @param SchemaValidatorInterface $validator
     *   Finds private settings and checks views against their schema.
     * @param StepRegistryInterface $steps
     *   Supplies the view steps.
     */
    public function __construct(
        private readonly BlockBehaviourRegistryInterface $behaviours,
        private readonly SchemaValidatorInterface $validator,
        private readonly StepRegistryInterface $steps,
    )
    {
    }

    /**
     * Builds one block's view; a failing behaviour or view schema yields no view and an issue, never an exception.
     *
     * A view step that throws records an issue and only its contribution is skipped.
     *
     * The view carries no editor information: the behaviour's admin values are handed back separately so the
     * resolver can attach the editor information to the resolved block, whether or not it reached a view.
     *
     * @return array{0: BlockView|null, 1: list<Issue>, 2: array<string, mixed>}
     *   The view, or null when the block has none; all of the block's issues including the earlier stages'; and
     *   the admin values the behaviour handed over, empty in {@link Mode::Front} or when there are none.
     *
     * @since 0.1.0
     */
    public function run(StageOutcome $validated, BlockType $type, Context $context, Mode $mode): array
    {
        $issues = $validated->issues;

        if (!$validated->continue)
        {
            return [null, $issues, []];
        }

        $blockId = $validated->block->id;
        $block = $validated->workingBlock();
        $behaviour = $this->behaviours->get($type->name);

        if ($behaviour === null)
        {
            $view = new BlockView($this->defaultValues($validated->settings, $type, $mode));
        }
        else
        {
            try
            {
                $view = $behaviour->view($block, $type, $validated->settings, $context->only($type->uses), $mode);
            }
            catch (Throwable $e)
            {
                $issues[] = new Issue($blockId, IssueKind::InvalidView, sprintf(
                    'The behaviour of "%s" failed with %s.',
                    $type->name->toString(),
                    $e::class,
                ));

                return [null, $issues, []];
            }
        }

        $adminValues = $mode === Mode::Admin ? ($view->admin?->values ?? []) : [];

        foreach ($view->childPasses ?? [] as $item)
        {
            if (!$item instanceof CurrentItem)
            {
                $issues[] = new Issue($blockId, IssueKind::InvalidView, sprintf(
                    'The child passes of "%s" must be current items.',
                    $type->name->toString(),
                ));

                return [null, $issues, $adminValues];
            }
        }

        if ($type->viewSchema !== null)
        {
            $errors = $this->validator->validate($view->values, $type->viewSchema)->errors();

            foreach ($errors as $error)
            {
                $issues[] = new Issue($blockId, IssueKind::InvalidView, $error->message, $error->path);
            }

            if ($errors !== [])
            {
                return [null, $issues, $adminValues];
            }
        }

        $view = $view->withAdmin(null);

        foreach ($this->steps->for(ViewStepInterface::class) as $step)
        {
            try
            {
                $extended = $step->extend($view, $block, $type, $validated->extensions, $context, $mode);
            }
            catch (Throwable $e)
            {
                $issues[] = new Issue($blockId, IssueKind::StepFailed, sprintf(
                    'View step %s failed with %s.',
                    get_debug_type($step),
                    $e::class,
                ));

                continue;
            }

            $view = new BlockView(
                $view->values,
                array_replace($view->extensions, $extended->extensions),
                null,
                $view->childPasses,
            );
        }

        return [$view, $issues, $adminValues];
    }

    /**
     * Uses the settings as the view, leaving private ones out on the front end so they never reach a page.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function defaultValues(array $settings, BlockType $type, Mode $mode): array
    {
        if ($mode === Mode::Admin)
        {
            return $settings;
        }

        return array_diff_key($settings, array_flip($this->validator->privateProperties($type->settingsSchema)));
    }
}
