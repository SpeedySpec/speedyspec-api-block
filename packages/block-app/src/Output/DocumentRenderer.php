<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Output;

use InvalidArgumentException;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\CollectStepInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\RenderStepInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\UnresolvedBlockOutputInterface;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidAttributeException;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\RenderResult;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;
use Throwable;

/**
 * Draws a resolved document by asking each block type's own output to draw its blocks.
 *
 * Nothing a block, step or output does can stop the rest of the document from being drawn: each failure is
 * recorded on the block where it happened and that block alone is left out.
 *
 * @since 0.1.0
 */
class DocumentRenderer implements DocumentRendererInterface
{
    /**
     * Takes every collaborator as a contract so each can be replaced on its own.
     *
     * @param UnresolvedBlockOutputInterface|null $unresolved
     *   Draws a stand-in, in admin mode, for a block that produced no view; without it such a block draws nothing.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly DocumentResolverInterface $resolver,
        private readonly BlockOutputRegistryInterface $outputs,
        private readonly OutputEscaperRegistryInterface $escapers,
        private readonly StepRegistryInterface $steps,
        private readonly ?UnresolvedBlockOutputInterface $unresolved = null,
    )
    {
    }

    /**
     * Resolves the document in the request's mode, then draws it.
     *
     * @since 0.1.0
     */
    public function render(Document $document, RenderRequest $request, ?Context $context = null): RenderResult
    {
        // Named so a block that includes other documents can tell it is being asked to include its own page.
        $context = ($context ?? Context::empty())->with('core/document', $document->id);

        return $this->renderResolved($this->resolver->resolve($document, $request->mode, $context), $request);
    }

    /**
     * Draws an already-resolved document, refusing one resolved for a different audience than the request names.
     *
     * @since 0.1.0
     */
    public function renderResolved(ResolvedDocument $resolved, RenderRequest $request): RenderResult
    {
        if ($resolved->mode !== $request->mode)
        {
            throw new InvalidArgumentException(sprintf(
                'A document resolved in %s mode cannot be drawn in %s mode.',
                $resolved->mode->value,
                $request->mode->value,
            ));
        }

        $escaper = $this->escapers->get($request->target);
        $issues = [];
        $assets = [];
        $output = '';

        foreach ($resolved->roots as $root)
        {
            $output .= $this->block($root, $request, $escaper, $issues, $assets);
        }

        return new RenderResult($output, $resolved, [...$resolved->issues, ...$issues], $assets);
    }

    /**
     * Draws one block and, through it, its children.
     *
     * @param list<Issue> $issues
     *   Collects the issues drawing raises, in tree order.
     * @param list<string> $assets
     *   Collects the asset names of drawn blocks, each once.
     *
     * @since 0.1.0
     */
    private function block(
        ResolvedBlock $block,
        RenderRequest $request,
        OutputEscaperInterface $escaper,
        array &$issues,
        array &$assets,
    ): string
    {
        if ($block->view === null || $block->type === null)
        {
            return $this->standIn($block, $request, $escaper, $issues);
        }

        $id = $block->block->id;
        $bag = $this->bag($block, $request, $issues);
        $output = $this->outputs->get($block->type->name, $request->mode, $request->target);

        if ($output === null)
        {
            $issues[] = new Issue($id, IssueKind::MissingOutput, sprintf(
                'Block type "%s" has no %s output for the "%s" target.',
                $block->type->name->toString(),
                $request->mode->value,
                $request->target,
            ));
        }

        $passes = [];

        foreach ($block->passes as $children)
        {
            $drawn = '';

            foreach ($children as $child)
            {
                $drawn .= $this->block($child, $request, $escaper, $issues, $assets);
            }

            $passes[] = $drawn;
        }

        if ($output === null)
        {
            return implode('', $passes);
        }

        try
        {
            $html = $output->render(new RenderInput(
                $id,
                $block->type,
                $block->view,
                $bag,
                $passes,
                $block->context,
                $request,
                $escaper,
                $request->mode === Mode::Admin ? $block->admin : null,
            ));
        }
        catch (Throwable $e)
        {
            $issues[] = new Issue($id, IssueKind::OutputFailed, sprintf(
                'The output of "%s" failed with %s.',
                $block->type->name->toString(),
                $e::class,
            ));

            return '';
        }

        $this->collect($block, $request, $issues, $assets);

        return $html;
    }

    /**
     * Draws the stand-in for a block with no view: nothing for a visitor, the placeholder in admin mode.
     *
     * @param list<Issue> $issues
     *
     * @since 0.1.0
     */
    private function standIn(
        ResolvedBlock $block,
        RenderRequest $request,
        OutputEscaperInterface $escaper,
        array &$issues,
    ): string
    {
        if ($this->unresolved === null || $request->mode !== Mode::Admin)
        {
            return '';
        }

        try
        {
            return $this->unresolved->render($block, $request, $escaper);
        }
        catch (Throwable $e)
        {
            $issues[] = new Issue($block->block->id, IssueKind::OutputFailed, sprintf(
                'The placeholder for "%s" failed with %s.',
                $block->block->type->toString(),
                $e::class,
            ));

            return '';
        }
    }

    /**
     * Lets every render step add to the block's attribute bag, keeping the bag as it was when a step misbehaves.
     *
     * @param list<Issue> $issues
     *
     * @since 0.1.0
     */
    private function bag(ResolvedBlock $block, RenderRequest $request, array &$issues): AttributeBag
    {
        $bag = AttributeBag::empty();

        foreach ($this->steps->for(RenderStepInterface::class) as $step)
        {
            try
            {
                $bag = $step->attributes($bag, $block, $request);
            }
            catch (InvalidAttributeException $e)
            {
                $issues[] = new Issue($block->block->id, IssueKind::InvalidAttribute, sprintf(
                    'Render step %s tried to add an attribute a block may not carry: %s',
                    get_debug_type($step),
                    $e->getMessage(),
                ));
            }
            catch (Throwable $e)
            {
                $issues[] = new Issue($block->block->id, IssueKind::StepFailed, sprintf(
                    'Render step %s failed with %s.',
                    get_debug_type($step),
                    $e::class,
                ));
            }
        }

        return $bag;
    }

    /**
     * Records the assets a drawn block needs: those its type declares, then those collect steps name.
     *
     * @param list<Issue> $issues
     * @param list<string> $assets
     *
     * @since 0.1.0
     */
    private function collect(ResolvedBlock $block, RenderRequest $request, array &$issues, array &$assets): void
    {
        $names = $block->type?->assets ?? [];

        foreach ($this->steps->for(CollectStepInterface::class) as $step)
        {
            try
            {
                $extra = $step->assets($block, $request);

                foreach ($extra as $name)
                {
                    if (!is_string($name))
                    {
                        throw new InvalidArgumentException('Asset names must be strings.');
                    }
                }
            }
            catch (Throwable $e)
            {
                $issues[] = new Issue($block->block->id, IssueKind::StepFailed, sprintf(
                    'Collect step %s failed with %s.',
                    get_debug_type($step),
                    $e::class,
                ));

                continue;
            }

            $names = [...$names, ...array_values($extra)];
        }

        foreach ($names as $name)
        {
            if (!in_array($name, $assets, true))
            {
                $assets[] = $name;
            }
        }
    }
}
