<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\Ports;

/**
 * What the adapter needs from WordPress's block bindings: the sources, and a value from one.
 *
 * @since 0.1.0
 */
interface WpBindingsInterface
{
    /**
     * Lists the names of the registered binding sources, such as `core/post-meta`.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    public function sources(): array;

    /**
     * Asks a source for a value.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     *   The block context values in scope.
     *
     * @return mixed
     *   Null when the source has no value.
     *
     * @since 0.1.0
     */
    public function value(string $source, array $arguments, array $context): mixed;
}
