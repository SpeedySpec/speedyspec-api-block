<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Holds the block templates defined in code, by block packages or by the host.
 *
 * Templates people save while building are a different thing with a different owner; they live in a
 * BlockTemplateRepositoryInterface, which needs storage. A builder lists both.
 *
 * @since 0.1.0
 */
interface BlockTemplateRegistryInterface
{
    /**
     * Registers a template, replacing one already registered under the same name.
     *
     * @since 0.1.0
     */
    public function add(BlockTemplate $template): void;

    /**
     * Removes the template registered under a name, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds a template by name.
     *
     * @return BlockTemplate|null
     *   Null when no template is registered under the name.
     *
     * @since 0.1.0
     */
    public function find(string $name): ?BlockTemplate;

    /**
     * Lists every registered template.
     *
     * @return list<BlockTemplate>
     *   Templates in registration order.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
