<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Documents;

use Closure;
use SpeedySpec\Block\Domain\Contracts\DocumentIncluderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
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
    public const int MAX_DEPTH = 8;

    public const int MAX_INCLUDES = 500;

    /**
     * Ids of the documents being drawn, outermost first.
     *
     * @var list<string>
     */
    private array $drawing = [];

    private int $included = 0;

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
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly Closure $renderer,
    )
    {
    }

    public function include(string $documentId, string $blockId, RenderRequest $request, Context $context): string
    {
        if ($this->drawing === [])
        {
            $this->included = 0;
        }

        $stopped = match (true)
        {
            in_array($documentId, $this->drawing, true) => sprintf(
                'Document "%s" includes itself, directly or through another document.',
                $documentId,
            ),
            count($this->drawing) >= self::MAX_DEPTH => sprintf(
                'Document "%s" is included more than %d documents deep.',
                $documentId,
                self::MAX_DEPTH,
            ),
            $this->included >= self::MAX_INCLUDES => sprintf(
                'Document "%s" was not drawn: the page already includes %d documents.',
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
            $document = $this->documents->find($documentId);

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
