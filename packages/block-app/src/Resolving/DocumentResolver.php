<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;

/**
 * Walks a document's tree running the five resolving stages on each block, passing context down to the children.
 *
 * Children are resolved only under a block that reached its view, so nothing below a hidden or broken block runs.
 * Each call resolves at most a budget of blocks, because nested repeating blocks multiply their per-block passes.
 *
 * @since 0.1.0
 */
class DocumentResolver implements DocumentResolverInterface
{
    /**
     * Caps the child passes a behaviour may ask for, so hostile content cannot make one block resolve without end.
     */
    private const MAX_PASSES = 1000;

    /**
     * Wires the resolver to its stages.
     *
     * @param PrepareStage $prepare
     *   Migrates and fills each block.
     * @param GateStage $gate
     *   Decides whether each block is shown.
     * @param ResolveStage $resolve
     *   Resolves each block's bindings.
     * @param ValidateStage $validate
     *   Checks each block's final settings.
     * @param ViewStage $view
     *   Builds each block's view.
     * @param BlockTypeRegistryInterface $types
     *   Finds the types whose placement rules are checked.
     * @param int $budget
     *   The most blocks one call resolves; the block that finds it spent gets an issue and nothing further runs.
     */
    public function __construct(
        private readonly PrepareStage $prepare,
        private readonly GateStage $gate,
        private readonly ResolveStage $resolve,
        private readonly ValidateStage $validate,
        private readonly ViewStage $view,
        private readonly BlockTypeRegistryInterface $types,
        private readonly PlacementRulesInterface $placement,
        private readonly int $budget = 50000,
    )
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function resolve(Document $document, Mode $mode, ?Context $context = null): ResolvedDocument
    {
        $context ??= Context::empty();
        $issues = [];
        $roots = [];
        $remaining = $this->budget;

        foreach ($document->tree->roots() as $root)
        {
            if ($remaining < 0)
            {
                break;
            }

            if ($this->placed($root, null, $issues))
            {
                $roots[] = $this->block($root, $context, $mode, $issues, $remaining);
            }
        }

        return new ResolvedDocument($document, $mode, $roots, $issues);
    }

    /**
     * Resolves one block and, when it reached a view, its children once per pass.
     *
     * @param list<Issue> $issues
     *   Receives the block's issues, then its descendants'.
     * @param int $remaining
     *   The blocks this call may still resolve; set below zero once the budget is spent, so callers stop.
     */
    private function block(
        Block $stored,
        Context $context,
        Mode $mode,
        array &$issues,
        int &$remaining,
    ): ResolvedBlock
    {
        if ($remaining === 0)
        {
            $remaining = -1;
            $issue = new Issue($stored->id, IssueKind::BudgetExceeded, sprintf(
                'Resolving stopped at block "%s": the document resolves at most %d blocks.',
                $stored->id,
                $this->budget,
            ));
            $issues[] = $issue;

            return new ResolvedBlock(
                $stored,
                $this->types->get($stored->type),
                $stored->settings,
                $stored->extensions,
                null,
                $context,
                $mode === Mode::Admin ? new AdminContext(issues: [$issue]) : null,
            );
        }

        $remaining--;
        $outcome = $this->prepare->run($stored, $context, $mode);
        $type = $this->types->get($stored->type);

        if ($type === null || !$outcome->continue)
        {
            array_push($issues, ...$outcome->issues);

            return new ResolvedBlock(
                $stored,
                $type,
                $outcome->settings,
                $outcome->extensions,
                null,
                $context,
                $this->admin($mode, $outcome, $outcome->issues, []),
            );
        }

        $outcome = $this->gate->run($outcome, $type, $context, $mode);
        $outcome = $this->resolve->run($outcome, $type, $context, $mode);
        $outcome = $this->validate->run($outcome, $type, $context, $mode);
        [$view, $blockIssues, $adminValues] = $this->view->run($outcome, $type, $context, $mode);

        if ($view === null)
        {
            array_push($issues, ...$blockIssues);
            $block = $outcome->continue ? $outcome->workingBlock() : $stored;

            return new ResolvedBlock(
                $block,
                $type,
                $outcome->settings,
                $outcome->extensions,
                null,
                $context,
                $this->admin($mode, $outcome, $blockIssues, $adminValues),
            );
        }

        $passContexts = $this->passContexts($stored, $type, $view, $context, $blockIssues);
        array_push($issues, ...$blockIssues);
        $passes = [];

        foreach ($passContexts as $index => $passContext)
        {
            if ($remaining < 0)
            {
                break;
            }

            $children = [];

            foreach ($stored->children as $child)
            {
                if ($remaining < 0)
                {
                    break;
                }

                // Placement does not depend on the pass, so it is reported once rather than once per pass.
                $discard = [];

                $allowed = $index === 0
                    ? $this->placed($child, $type, $issues)
                    : $this->placed($child, $type, $discard);

                if ($allowed)
                {
                    $children[] = $this->block($child, $passContext, $mode, $issues, $remaining);
                }
            }

            $passes[] = $children;
        }

        return new ResolvedBlock(
            $outcome->workingBlock(),
            $type,
            $outcome->settings,
            $outcome->extensions,
            $view,
            $context,
            $this->admin($mode, $outcome, $blockIssues, $adminValues),
            $passes,
        );
    }

    /**
     * Assembles the editor information of one block, so even a block that stopped can show the editor why.
     *
     * @param list<Issue> $blockIssues
     *   Every issue recorded against the block itself.
     * @param array<string, mixed> $values
     *   The admin values the behaviour handed over.
     */
    private function admin(Mode $mode, StageOutcome $outcome, array $blockIssues, array $values): ?AdminContext
    {
        if ($mode !== Mode::Admin)
        {
            return null;
        }

        return new AdminContext($values, $blockIssues, $outcome->bindingInfo, $outcome->hiddenOnFront);
    }

    /**
     * Derives the context of each child pass from what the block received and what its view provides.
     *
     * @param list<Issue> $issues
     *   Receives the issue when the view asks for more passes than allowed.
     *
     * @return list<Context>
     */
    private function passContexts(
        Block $block,
        BlockType $type,
        BlockView $view,
        Context $context,
        array &$issues,
    ): array
    {
        foreach ($type->provides as $key => $viewKey)
        {
            if (array_key_exists($viewKey, $view->values))
            {
                $context = $context->with((string) $key, $view->values[$viewKey]);
            }
        }

        if ($view->childPasses === null)
        {
            return [$context];
        }

        if (count($view->childPasses) > self::MAX_PASSES)
        {
            $issues[] = new Issue($block->id, IssueKind::InvalidView, sprintf(
                'The view of "%s" asked for %d child passes; only the first %d are resolved.',
                $type->name->toString(),
                count($view->childPasses),
                self::MAX_PASSES,
            ));
        }

        $contexts = [];

        foreach (array_slice(array_values($view->childPasses), 0, self::MAX_PASSES) as $item)
        {
            $contexts[] = $context->withCurrentItem($item);
        }

        return $contexts;
    }

    /**
     * Checks a block may sit under the given parent type, recording why when it may not.
     *
     * The decision belongs to the placement contract, which builders use too, so the two can never disagree.
     *
     * @param list<Issue> $issues
     *   Receives the issue when the block is misplaced.
     */
    private function placed(Block $block, ?BlockType $parent, array &$issues): bool
    {
        $allowed = $this->placement->decide($block->type, $parent?->name)->allowed;

        if (!$allowed)
        {
            $issues[] = new Issue($block->id, IssueKind::DisallowedChild, sprintf(
                'Block type "%s" may not be placed %s.',
                $block->type->toString(),
                $parent === null ? 'at the document root' : sprintf('inside "%s"', $parent->name->toString()),
            ));
        }

        return $allowed;
    }
}
