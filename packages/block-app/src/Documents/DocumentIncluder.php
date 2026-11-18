<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Documents;

use Closure;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentIncluderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use Throwable;

/**
 * Draws stored documents inside others through the ordinary renderer, keeping track of what is being drawn.
 *
 * Three limits keep a page finite whatever the stored documents say: a document already being drawn further up
 * is not drawn again, inclusions nest at most MAX_DEPTH deep, and one inclusion from a page may lead to at most
 * MAX_INCLUDES others.
 *
 * @since 0.1.0
 */
class DocumentIncluder implements DocumentIncluderInterface
{
    /**
     * The context key under which the renderer names the document it is drawing.
     */
    public const string PAGE = 'core/document';

    public const int MAX_DEPTH = 8;

    public const int MAX_INCLUDES = 500;

    /**
     * Ids of the documents being drawn, outermost first.
     *
     * @var list<string>
     */
    private array $drawing = [];

    private int $included = 0;

    private ?string $page = null;

    /**
     * @var list<Issue>
     */
    private array $issues = [];

    /**
     * @var list<string>
     */
    private array $assets = [];

    /**
     * @param Closure(): DocumentRendererInterface $renderer
     *   Gets the renderer when it is first needed; the renderer and the includer each need the other, so neither
     *   can be handed to the other when it is built.
     * @param BuilderAccessInterface|null $access
     *   Asked before a document is drawn inside another, so including a document is never a way to read one the
     *   person may not see. Without it every stored document may be included.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly Closure $renderer,
        private readonly ?BuilderAccessInterface $access = null,
    )
    {
    }

    public function include(string $documentId, string $blockId, RenderRequest $request, Context $context): string
    {
        if ($this->drawing === [])
        {
            $this->included = 0;
        }

        // The page being drawn is not an inclusion, so it is not on the stack; the renderer names it in the context
        // of its blocks, and it is remembered for as long as anything included from it is being drawn.
        if ($this->drawing === [])
        {
            $this->page = is_string($context->get(self::PAGE)) ? $context->get(self::PAGE) : null;
        }

        $page = $this->page;

        $stopped = match (true)
        {
            $documentId === $page, in_array($documentId, $this->drawing, true) => sprintf(
                'Document "%s" includes itself, directly or through another document.',
                $documentId,
            ),
            count($this->drawing) >= self::MAX_DEPTH => sprintf(
                'Document "%s" is included more than %d documents deep.',
                $documentId,
                self::MAX_DEPTH,
            ),
            $this->included >= self::MAX_INCLUDES => sprintf(
                'Document "%s" was not drawn: this inclusion already led to %d others.',
                $documentId,
                self::MAX_INCLUDES,
            ),
            default => null,
        };

        if ($stopped !== null)
        {
            $this->issues[] = new Issue($blockId, IssueKind::DocumentLoop, $stopped, 'document');

            return '';
        }

        $this->included++;
        $this->drawing[] = $documentId;

        try
        {
            $ability = $request->mode === Mode::Admin ? BuilderAbility::Edit : BuilderAbility::View;
            $allowed = $this->access === null || $this->access->allows($ability, $documentId);
            $document = $allowed ? $this->documents->find($documentId) : null;

            // A document the person may not see is reported exactly as one that is not stored.
            if ($document === null)
            {
                $this->issues[] = new Issue(
                    $blockId,
                    IssueKind::UnknownDocument,
                    sprintf('Document "%s" is not stored.', $documentId),
                    'document',
                );

                return '';
            }

            $result = ($this->renderer)()->render($document, $request, $context);
        }
        catch (Throwable $e)
        {
            $this->issues[] = new Issue(
                $blockId,
                IssueKind::UnknownDocument,
                sprintf('Document "%s" could not be drawn: %s.', $documentId, $e::class),
                'document',
            );

            return '';
        }
        finally
        {
            array_pop($this->drawing);
        }

        $this->issues = [...$this->issues, ...$result->issues];

        foreach ($result->assets as $asset)
        {
            if (!in_array($asset, $this->assets, true))
            {
                $this->assets[] = $asset;
            }
        }

        return $result->output;
    }

    public function issues(): array
    {
        return $this->issues;
    }

    public function assets(): array
    {
        return $this->assets;
    }

    public function clear(): void
    {
        $this->issues = [];
        $this->assets = [];
    }
}
