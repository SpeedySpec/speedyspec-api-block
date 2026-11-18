<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Names the assets an output needs on the page, so they come and go with the output that uses them.
 *
 * Implemented, optionally, by a {@link BlockOutputInterface}. A plugin that redraws a block brings its own scripts
 * and styles, and the replaced output's stop being collected.
 *
 * @since 0.1.0
 */
interface DeclaresAssetsInterface
{
    /**
     * Lists the names of the assets the output needs.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    public function assets(): array;
}
