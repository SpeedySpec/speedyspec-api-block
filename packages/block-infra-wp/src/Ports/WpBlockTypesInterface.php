<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\Ports;

/**
 * What the adapter needs from WordPress's block type registry: each type as plain data.
 *
 * @since 0.1.0
 */
interface WpBlockTypesInterface
{
    /**
     * Lists every registered block type.
     *
     * @return list<array<string, mixed>>
     *   One map per type with the keys WordPress uses: `name`, `title`, `description`, `category`, `keywords`,
     *   `attributes`, `parent`, `allowed_blocks`, `provides_context`, `uses_context`, `example`, and `dynamic`,
     *   true when the type is drawn by a render callback.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
