<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Building;

use RuntimeException;
use SpeedySpec\Block\Domain\Contracts\BlockDuplicatorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockIdGeneratorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusal;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusalKind;
use SpeedySpec\Block\Domain\ValueObjects\EditResult;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

/**
 * Applies editing operations only when the document that results is one the resolver will accept.
 *
 * It checks what a builder would otherwise have to re-implement: positions, the placement rules, settings against
 * their schema, and bindings against the data source and the setting they feed.
 *
 * @since 0.1.0
 */
class DocumentEditor implements DocumentEditorInterface
{
    private const int MAX_ID_ATTEMPTS = 100;

    /**
     * Takes the registries it checks against; all are read at call time, so later registrations count.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly BlockTypeRegistryInterface $types,
        private readonly PlacementRulesInterface $placement,
        private readonly SchemaValidatorInterface $validator,
        private readonly DecoratorRegistryInterface $decorators,
        private readonly DataSourceRegistryInterface $sources,
        private readonly BlockIdGeneratorInterface $ids,
        private readonly BlockDuplicatorInterface $duplicator,
    )
    {
    }

    public function insert(Document $document, BlockName $type, Position $position, array $settings = []): EditResult
    {
        $refusal = $this->positionRefusal($document->tree, $position);

        if ($refusal !== null)
        {
            return EditResult::refused($refusal);
        }

        $blockType = $this->types->get($type);

        if ($blockType === null)
        {
            return EditResult::refused($this->unknownType($type, null));
        }

        $refusals = [
            ...$this->placementRefusals($type, $this->parentType($document->tree, $position), null),
            ...$this->settingsRefusals($settings, $blockType->settingsSchema, null),
        ];

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        $block = new Block($this->freshId($document->tree), $type, $blockType->version, $settings);

        return EditResult::accepted($document->withTree($document->tree->insert($block, $position)), [$block->id]);
    }

    public function insertBlocks(Document $document, array $blocks, Position $position): EditResult
    {
        $refusal = $this->positionRefusal($document->tree, $position);

        if ($refusal !== null)
        {
            return EditResult::refused($refusal);
        }

        $parentType = $this->parentType($document->tree, $position);
        $refusals = [];

        foreach ($blocks as $block)
        {
            $refusals = [...$refusals, ...$this->subtreeRefusals($block, $parentType)];
        }

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        $tree = $document->tree;
        $affected = [];

        foreach (array_values($blocks) as $offset => $block)
        {
            $copy = $this->duplicator->duplicate($block, $tree->ids());
            $at = $position->parentId === null
                ? Position::root($position->index + $offset)
                : Position::inside($position->parentId, $position->index + $offset);
            $tree = $tree->insert($copy, $at);
            $affected = [...$affected, ...(new BlockTree($copy))->ids()];
        }

        return EditResult::accepted($document->withTree($tree), $affected);
    }

    public function insertTemplate(Document $document, BlockTemplate $template, Position $position): EditResult
    {
        return $this->insertBlocks($document, $template->tree->roots(), $position);
    }

    public function move(Document $document, string $blockId, Position $position): EditResult
    {
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        if ($position->parentId !== null && in_array($position->parentId, (new BlockTree($block))->ids(), true))
        {
            return EditResult::refused(new EditRefusal(
                EditRefusalKind::MoveIntoSelf,
                sprintf('Block "%s" cannot be moved inside itself or one of its own descendants.', $blockId),
                $blockId,
            ));
        }

        $without = $document->tree->remove($blockId);
        $refusal = $this->positionRefusal($without, $position);

        if ($refusal !== null)
        {
            return EditResult::refused($refusal);
        }

        $refusals = $this->placementRefusals($block->type, $this->parentType($without, $position), $blockId);

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return EditResult::accepted($document->withTree($without->insert($block, $position)), [$blockId]);
    }

    public function remove(Document $document, string $blockId): EditResult
    {
        if ($document->tree->find($blockId) === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        return EditResult::accepted($document->withTree($document->tree->remove($blockId)));
    }

    public function duplicate(Document $document, string $blockId): EditResult
    {
        $block = $document->tree->find($blockId);
        $position = $document->tree->positionOf($blockId);

        if ($block === null || $position === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        $copy = $this->duplicator->duplicate($block, $document->tree->ids());
        $after = $position->parentId === null
            ? Position::root($position->index + 1)
            : Position::inside($position->parentId, $position->index + 1);

        return EditResult::accepted(
            $document->withTree($document->tree->insert($copy, $after)),
            (new BlockTree($copy))->ids(),
        );
    }

    public function updateSettings(Document $document, string $blockId, array $settings): EditResult
    {
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        $type = $this->types->get($block->type);

        if ($type === null)
        {
            return EditResult::refused($this->unknownType($block->type, $blockId));
        }

        $refusals = $this->settingsRefusals($settings, $type->settingsSchema, $blockId);

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return $this->replaced($document, $block->withSettings($settings)->withTypeVersion($type->version));
    }

    public function updateExtension(
        Document $document,
        string $blockId,
        string $namespace,
        array $settings,
    ): EditResult
    {
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        $decorator = $this->decorators->get($namespace);

        if ($decorator === null || !$decorator->appliesTo($block->type))
        {
            return EditResult::refused(new EditRefusal(
                EditRefusalKind::UnknownDecorator,
                sprintf('No decorator "%s" applies to block type "%s".', $namespace, $block->type->toString()),
                $blockId,
            ));
        }

        $refusals = $this->settingsRefusals($settings, $decorator->settingsSchema, $blockId, $namespace);

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return $this->replaced($document, $block->withExtension($namespace, $settings));
    }

    public function setBinding(Document $document, string $blockId, SettingPath $path, Binding $binding): EditResult
    {
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        $schema = $this->schemaFor($block, $path);
        $setting = $schema === null ? null : $this->validator->subschema($schema, $path->segments());

        if ($schema === null || $setting === null)
        {
            return EditResult::refused($this->invalidBinding(
                sprintf('"%s" is not a setting block "%s" declares.', $path->toString(), $blockId),
                $blockId,
                $path,
            ));
        }

        if (in_array($path->segments()[0], $this->validator->privateProperties($schema), true))
        {
            return EditResult::refused(new EditRefusal(
                EditRefusalKind::PrivateBinding,
                sprintf('"%s" is private and cannot be bound to a data source.', $path->toString()),
                $blockId,
                $path->toString(),
            ));
        }

        $refusals = [];
        $source = $this->sources->get($binding->source);

        if ($source === null)
        {
            $refusals[] = new EditRefusal(
                EditRefusalKind::UnknownDataSource,
                sprintf('No data source "%s" is registered.', $binding->source),
                $blockId,
                $path->toString(),
            );
        }
        else
        {
            $arguments = $source->argumentsSchema();
            $checked = $this->validator->validate(
                $this->validator->withDefaults($binding->arguments, $arguments),
                $arguments,
            );

            foreach ($checked->errors() as $error)
            {
                $refusals[] = $this->invalidBinding(
                    sprintf('Argument "%s" of "%s": %s', $error->path, $binding->source, $error->message),
                    $blockId,
                    $path,
                );
            }
        }

        if ($binding->hasFallback)
        {
            foreach ($this->validator->validate($binding->fallback, $setting)->errors() as $error)
            {
                $refusals[] = $this->invalidBinding(
                    sprintf('The fallback does not fit "%s": %s', $path->toString(), $error->message),
                    $blockId,
                    $path,
                );
            }
        }

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return $this->replaced($document, $block->withBinding($path, $binding));
    }

    public function clearBinding(Document $document, string $blockId, SettingPath $path): EditResult
    {
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        return $this->replaced($document, $block->withoutBinding($path));
    }

    /**
     * Swaps one changed block into the document.
     *
     * @since 0.1.0
     */
    private function replaced(Document $document, Block $block): EditResult
    {
        return EditResult::accepted($document->withTree($document->tree->replace($block->id, $block)), [$block->id]);
    }

    /**
     * Finds the schema a setting path is read against: the block type's, or an applicable decorator's.
     *
     * @return array<string, mixed>|null
     *   Null when the type is not registered or no such decorator applies to the block.
     *
     * @since 0.1.0
     */
    private function schemaFor(Block $block, SettingPath $path): ?array
    {
        if ($path->namespace() === null)
        {
            return $this->types->get($block->type)?->settingsSchema;
        }

        $decorator = $this->decorators->get($path->namespace());

        return $decorator !== null && $decorator->appliesTo($block->type) ? $decorator->settingsSchema : null;
    }

    /**
     * Checks a block about to be placed, then its descendants, in tree order.
     *
     * @param BlockName|null $parent
     *   The type the block would sit inside; null at the document root.
     *
     * @return list<EditRefusal>
     *
     * @since 0.1.0
     */
    private function subtreeRefusals(Block $block, ?BlockName $parent): array
    {
        $type = $this->types->get($block->type);
        $refusals = [];

        if ($type === null)
        {
            $refusals[] = $this->unknownType($block->type, $block->id);
        }
        else
        {
            $refusals = [
                ...$this->placementRefusals($block->type, $parent, $block->id),
                ...$this->settingsRefusals($block->settings, $type->settingsSchema, $block->id),
            ];
        }

        foreach ($block->children as $child)
        {
            $refusals = [...$refusals, ...$this->subtreeRefusals($child, $block->type)];
        }

        return $refusals;
    }

    /**
     * Checks a position names a parent in the tree and an index no further than one past its last child.
     *
     * @since 0.1.0
     */
    private function positionRefusal(BlockTree $tree, Position $position): ?EditRefusal
    {
        if ($position->parentId === null)
        {
            $count = count($tree->roots());
        }
        else
        {
            $parent = $tree->find($position->parentId);

            if ($parent === null)
            {
                return new EditRefusal(
                    EditRefusalKind::InvalidPosition,
                    sprintf('No block "%s" to place a block inside.', $position->parentId),
                    $position->parentId,
                );
            }

            $count = count($parent->children);
        }

        if ($position->index > $count)
        {
            return new EditRefusal(
                EditRefusalKind::InvalidPosition,
                sprintf('Index %d is past the end of the %d blocks there.', $position->index, $count),
                $position->parentId,
            );
        }

        return null;
    }

    /**
     * Names the type of the block a position sits inside; null at the document root.
     *
     * @since 0.1.0
     */
    private function parentType(BlockTree $tree, Position $position): ?BlockName
    {
        return $position->parentId === null ? null : $tree->find($position->parentId)?->type;
    }

    /**
     * @return list<EditRefusal>
     *
     * @since 0.1.0
     */
    private function placementRefusals(BlockName $child, ?BlockName $parent, ?string $blockId): array
    {
        $decision = $this->placement->decide($child, $parent);

        if ($decision->allowed)
        {
            return [];
        }

        return [new EditRefusal(EditRefusalKind::DisallowedPlacement, (string) $decision->message, $blockId)];
    }

    /**
     * Judges settings as the resolver will: with the schema's defaults filled in, though they are stored as given.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $schema
     * @param string|null $namespace
     *   Set for a decorator's settings, so each path is reported as `@namespace:path`.
     *
     * @return list<EditRefusal>
     *
     * @since 0.1.0
     */
    private function settingsRefusals(
        array $settings,
        array $schema,
        ?string $blockId,
        ?string $namespace = null,
    ): array
    {
        $result = $this->validator->validate($this->validator->withDefaults($settings, $schema), $schema);
        $refusals = [];

        foreach ($result->errors() as $error)
        {
            if ($namespace === null)
            {
                $path = $error->path === '' ? null : $error->path;
            }
            else
            {
                $path = $error->path === '' ? '@' . $namespace : '@' . $namespace . ':' . $error->path;
            }

            $refusals[] = new EditRefusal(EditRefusalKind::InvalidSettings, $error->message, $blockId, $path);
        }

        return $refusals;
    }

    private function unknownBlock(string $blockId): EditRefusal
    {
        return new EditRefusal(
            EditRefusalKind::UnknownBlock,
            sprintf('The document has no block "%s".', $blockId),
            $blockId,
        );
    }

    private function unknownType(BlockName $type, ?string $blockId): EditRefusal
    {
        return new EditRefusal(
            EditRefusalKind::UnknownType,
            sprintf('Block type "%s" is not registered.', $type->toString()),
            $blockId,
        );
    }

    private function invalidBinding(string $message, string $blockId, SettingPath $path): EditRefusal
    {
        return new EditRefusal(EditRefusalKind::InvalidBinding, $message, $blockId, $path->toString());
    }

    /**
     * Generates an id no block in the tree uses.
     *
     * @throws RuntimeException
     *   When the generator keeps producing ids already in use.
     *
     * @since 0.1.0
     */
    private function freshId(BlockTree $tree): string
    {
        $used = array_fill_keys($tree->ids(), true);

        for ($attempt = 0; $attempt < self::MAX_ID_ATTEMPTS; $attempt++)
        {
            $id = $this->ids->generate();

            if (!isset($used[$id]))
            {
                return $id;
            }
        }

        throw new RuntimeException(sprintf(
            'The block id generator failed to produce an unused id in %d attempts.',
            self::MAX_ID_ATTEMPTS,
        ));
    }
}
