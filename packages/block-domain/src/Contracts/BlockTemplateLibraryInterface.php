<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * A named collection of block templates from one place: built in, saved by people, or fetched from a service.
 *
 * A builder lists libraries and shows each one's templates; where they come from is the library's business.
 *
 * @since 0.1.0
 */
interface BlockTemplateLibraryInterface
{
    /**
     * Names the library as lower-case `vendor/name`.
     *
     * @since 0.1.0
     */
    public function name(): string;

    /**
     * Gives the name a builder shows for the library.
     *
     * @since 0.1.0
     */
    public function title(): string;

    /**
     * Lists the library's templates.
     *
     * @return list<BlockTemplate>
     *
     * @since 0.1.0
     */
    public function all(): array;

    /**
     * Finds one of the library's templates by name.
     *
     * @return BlockTemplate|null
     *   Null when the library has no template under the name.
     *
     * @since 0.1.0
     */
    public function find(string $name): ?BlockTemplate;
}
