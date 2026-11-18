<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\WordPress;

use SpeedySpec\Block\Infra\WP\Ports\WpBlockTypesInterface;

/**
 * Reads the block types WordPress has registered.
 *
 * @since 0.1.0
 */
class WordPressBlockTypes implements WpBlockTypesInterface
{
    public function all(): array
    {
        $types = [];

        foreach (\WP_Block_Type_Registry::get_instance()->get_all_registered() as $type)
        {
            $types[] = [
                'name' => $type->name,
                'title' => $type->title,
                'description' => $type->description,
                'category' => $type->category,
                'keywords' => $type->keywords,
                'attributes' => $type->attributes,
                'parent' => $type->parent,
                'allowed_blocks' => $type->allowed_blocks,
                'provides_context' => $type->provides_context,
                'uses_context' => $type->uses_context,
                'example' => $type->example,
                'dynamic' => $type->is_dynamic(),
            ];
        }

        return $types;
    }
}
