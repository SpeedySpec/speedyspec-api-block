<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use InvalidArgumentException;
use SpeedySpec\Block\Domain\Entities\Document;

/**
 * The outcome of one editing operation: the new document, or every reason it was refused.
 *
 * @since 0.1.0
 */
class EditResult
{
    /**
     * @param list<EditRefusal> $refusals
     * @param list<string> $affectedIds
     *
     * @since 0.1.0
     */
    private function __construct(
        public readonly ?Document $document,
        public readonly array $refusals,
        public readonly array $affectedIds,
    )
    {
    }

    /**
     * Reports an edit that was applied.
     *
     * @param list<string> $affectedIds
     *   Ids of the blocks the operation added or changed, in tree order, so a builder knows what to select or
     *   redraw.
     *
     * @since 0.1.0
     */
    public static function accepted(Document $document, array $affectedIds = []): self
    {
        return new self($document, [], array_values($affectedIds));
    }

    /**
     * Reports an edit that was not applied.
     *
     * @throws InvalidArgumentException
     *   When no reason is given; a refusal without a reason cannot be acted on.
     *
     * @since 0.1.0
     */
    public static function refused(EditRefusal ...$refusals): self
    {
        if ($refusals === [])
        {
            throw new InvalidArgumentException('A refused edit needs at least one reason.');
        }

        return new self(null, array_values($refusals), []);
    }

    /**
     * Tells whether the edit was applied and a new document is available.
     *
     * @since 0.1.0
     */
    public function isAccepted(): bool
    {
        return $this->document !== null;
    }
}
