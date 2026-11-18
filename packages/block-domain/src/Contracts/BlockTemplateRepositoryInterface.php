<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Stores the block templates people save while building.
 *
 * @since 0.1.0
 */
interface BlockTemplateRepositoryInterface
{
    /**
     * Stores a template, replacing one already stored under the same name without changing its place in the list.
     *
     * @since 0.1.0
     */
    public function save(BlockTemplate $template): void;

    /**
     * Removes the template stored under a name, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds a stored template by name.
     *
     * @return BlockTemplate|null
     *   Null when no template is stored under the name.
     *
     * @since 0.1.0
     */
    public function find(string $name): ?BlockTemplate;

    /**
     * Lists every stored template.
     *
     * @return list<BlockTemplate>
     *   Templates in the order they were first saved.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
