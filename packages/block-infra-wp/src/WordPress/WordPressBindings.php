<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\WordPress;

use SpeedySpec\Block\Infra\WP\Ports\WpBindingsInterface;

/**
 * Reads values from the block bindings sources WordPress has registered.
 *
 * @since 0.1.0
 */
class WordPressBindings implements WpBindingsInterface
{
    public function sources(): array
    {
        return array_keys(\get_all_registered_block_bindings_sources());
    }

    public function value(string $source, array $arguments, array $context): mixed
    {
        $registered = \get_block_bindings_source($source);

        if ($registered === null)
        {
            return null;
        }

        // A source is given the block it is asked for; one built from the context alone is enough for the
        // sources WordPress ships, which read only the context.
        return $registered->get_value($arguments, new \WP_Block(['blockName' => 'core/paragraph'], $context), '');
    }
}
