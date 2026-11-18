<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTemplateException;

/**
 * A named, saved arrangement of blocks that can be inserted into a document.
 *
 * The ids of a template's blocks only identify them inside the template; inserting a template gives every block a
 * new id, so the same template can be inserted any number of times.
 *
 * @since 0.1.0
 */
class BlockTemplate
{
    /**
     * Checks the template can be listed and has something to insert.
     *
     * @param string $name
     *   Lower-case `vendor/name`, like a block type name.
     *
     * @throws InvalidBlockTemplateException
     *   When the name is malformed, the title is blank, or the tree has no blocks.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly BlockTree $tree,
        public readonly string $description = '',
        public readonly string $category = '',
    )
    {
        if (preg_match('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*\z/', $name) !== 1)
        {
            throw new InvalidBlockTemplateException(
                sprintf('"%s" is not a valid template name; expected lower-case "vendor/name".', $name)
            );
        }

        if (trim($title) === '')
        {
            throw new InvalidBlockTemplateException(sprintf('Template "%s" needs a title.', $name));
        }

        if ($tree->roots() === [])
        {
            throw new InvalidBlockTemplateException(sprintf('Template "%s" needs at least one block.', $name));
        }
    }
}
