<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Serialization;

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ClientExportInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidSettingPathException;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

/**
 * Strips what a visitor must not see: private settings, and every setting of a block whose type is unknown
 * because nothing says which of its settings are safe.
 *
 * @since 0.1.0
 */
class ClientExport implements ClientExportInterface
{
    /**
     * @param BlockTypeRegistryInterface $types
     *   Tells which block types are known.
     * @param SchemaValidatorInterface $validator
     *   Finds the private settings in a type's schema.
     */
    public function __construct(
        private readonly BlockTypeRegistryInterface $types,
        private readonly SchemaValidatorInterface $validator,
    )
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function export(Document $document, Mode $mode): Document
    {
        if ($mode === Mode::Admin)
        {
            return $document;
        }

        return $document->withTree(new BlockTree(...array_map($this->front(...), $document->tree->roots())));
    }

    /**
     * Cleans one block and, whatever its own type, its children.
     */
    private function front(Block $block): Block
    {
        $type = $this->types->get($block->type);
        $children = array_map($this->front(...), $block->children);

        if ($type === null)
        {
            return new Block($block->id, $block->type, $block->typeVersion, [], $block->extensions, [], $children);
        }

        $private = $this->validator->privateProperties($type->settingsSchema);
        $settings = array_diff_key($block->settings, array_flip($private));
        $bindings = array_filter(
            $block->bindings,
            static function (string|int $path) use ($private): bool {
                try
                {
                    $parsed = SettingPath::fromString((string) $path);
                }
                catch (InvalidSettingPathException)
                {
                    // A path that cannot be read cannot be shown to be safe.
                    return false;
                }

                return $parsed->namespace() !== null || !in_array($parsed->segments()[0], $private, true);
            },
            ARRAY_FILTER_USE_KEY,
        );

        return new Block(
            $block->id,
            $block->type,
            $block->typeVersion,
            $settings,
            $block->extensions,
            $bindings,
            $children,
        );
    }
}
