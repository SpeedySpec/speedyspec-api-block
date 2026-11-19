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
use SpeedySpec\Block\Domain\Contracts\RichTextSanitizerInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidSettingPathException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockLock;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusal;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusalKind;
use SpeedySpec\Block\Domain\ValueObjects\EditResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use Throwable;

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
        private readonly SettingsMigrationRegistryInterface $migrations,
        private readonly StepRegistryInterface $steps,
        private readonly ?RichTextSanitizerInterface $richText = null,
    )
    {
    }

    public function insert(Document $document, BlockName $type, Position $position, array $settings = []): EditResult
    {
        $refusal = $this->positionRefusal($document->tree, $position)
            ?? $this->parentRefusal($document->tree, $position);

        if ($refusal !== null)
        {
            return EditResult::refused($refusal);
        }

        $blockType = $this->types->get($type);

        if ($blockType === null)
        {
            return EditResult::refused($this->unknownType($type, null));
        }

        $settings = $this->cleaned($settings, $blockType->settingsSchema);
        $refusals = [
            ...$this->placementRefusals($type, $this->parentType($document->tree, $position), null),
            ...$this->settingsRefusals($settings, $blockType->settingsSchema, null),
        ];

        $block = new Block($this->freshId($document->tree), $type, $blockType->version, $settings);
        $refusals = $refusals !== [] ? $refusals : $this->stepRefusals($block, $blockType, null);

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return EditResult::accepted($document->withTree($document->tree->insert($block, $position)), [$block->id]);
    }

    public function insertBlocks(Document $document, array $blocks, Position $position): EditResult
    {
        $refusal = $this->positionRefusal($document->tree, $position)
            ?? $this->parentRefusal($document->tree, $position);

        if ($refusal !== null)
        {
            return EditResult::refused($refusal);
        }

        $parentType = $this->parentType($document->tree, $position);
        $refusals = [];
        $vetted = [];

        foreach ($blocks as $block)
        {
            if (!$block instanceof Block)
            {
                $refusals[] = new EditRefusal(
                    EditRefusalKind::InvalidBlock,
                    sprintf('Only blocks can be inserted; one entry is %s.', get_debug_type($block)),
                );

                continue;
            }

            $vetted[] = $this->vetted($block, $parentType, $refusals);
        }

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        $tree = $document->tree;
        $affected = [];

        foreach ($vetted as $offset => $block)
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

    public function restore(Document $document, array $blocks): EditResult
    {
        $refusals = [];
        $vetted = [];
        $seen = [];

        foreach ($blocks as $block)
        {
            if (!$block instanceof Block)
            {
                $refusals[] = new EditRefusal(
                    EditRefusalKind::InvalidBlock,
                    sprintf('Only blocks can be restored; one entry is %s.', get_debug_type($block)),
                );

                continue;
            }

            foreach ($this->subtreeIds($block) as $id)
            {
                if (isset($seen[$id]))
                {
                    $refusals[] = new EditRefusal(
                        EditRefusalKind::InvalidBlock,
                        sprintf('Block id "%s" is used more than once.', $id),
                        $id,
                    );
                }

                $seen[$id] = true;
            }

            $vetted[] = $this->vetted($block, null, $refusals);
        }

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return EditResult::accepted($document->withTree(new BlockTree(...$vetted)), array_keys($seen));
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

        if (BlockLock::of($block)->move)
        {
            return EditResult::refused(new EditRefusal(
                EditRefusalKind::Locked,
                'This block is locked in place.',
                $blockId,
            ));
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
        $refusal = $this->positionRefusal($without, $position) ?? $this->parentRefusal($without, $position);

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
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        $refusals = $this->removalLocks($block);

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return EditResult::accepted($document->withTree($document->tree->remove($blockId)));
    }

    /**
     * Finds the blocks locked against removal in a subtree about to be removed, so removing a parent cannot take a
     * locked block with it.
     *
     * @return list<EditRefusal>
     */
    private function removalLocks(Block $block, bool $inside = false): array
    {
        $message = $inside
            ? 'A block inside this one is locked against removal.'
            : 'This block is locked against removal.';
        $refusals = BlockLock::of($block)->remove
            ? [new EditRefusal(EditRefusalKind::Locked, $message, $block->id)]
            : [];

        foreach ($block->children as $child)
        {
            $refusals = [...$refusals, ...$this->removalLocks($child, true)];
        }

        return $refusals;
    }

    public function removeBlocks(Document $document, array $blockIds): EditResult
    {
        if ($blockIds === [])
        {
            return EditResult::refused(new EditRefusal(
                EditRefusalKind::UnknownBlock,
                'Name at least one block to remove.',
            ));
        }

        $unknown = [];
        $locks = [];

        foreach ($blockIds as $blockId)
        {
            $block = is_string($blockId) ? $document->tree->find($blockId) : null;

            if ($block === null)
            {
                $unknown[] = $this->unknownBlock(is_string($blockId) ? $blockId : get_debug_type($blockId));

                continue;
            }

            foreach ($this->removalLocks($block) as $refusal)
            {
                // A locked block inside two listed blocks is reported once; the prefix keeps a numeric id a string
                // key, so it can never collide with a list index.
                $locks['#' . $refusal->blockId] ??= $refusal;
            }
        }

        $refusals = [...$unknown, ...array_values($locks)];

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        $tree = $document->tree;

        foreach ($blockIds as $blockId)
        {
            // A block inside one already removed has gone with it.
            if ($tree->find($blockId) !== null)
            {
                $tree = $tree->remove($blockId);
            }
        }

        return EditResult::accepted($document->withTree($tree));
    }

    public function lockBlocks(Document $document, array $blockIds, BlockLock $lock): EditResult
    {
        if ($blockIds === [])
        {
            return EditResult::refused(new EditRefusal(
                EditRefusalKind::UnknownBlock,
                'Name at least one block to lock or unlock.',
            ));
        }

        $refusals = [];

        foreach ($blockIds as $blockId)
        {
            if (!is_string($blockId) || $document->tree->find($blockId) === null)
            {
                $refusals[] = $this->unknownBlock(is_string($blockId) ? $blockId : get_debug_type($blockId));
            }
        }

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        $tree = $document->tree;
        $affected = [];

        foreach (array_unique($blockIds) as $blockId)
        {
            $block = $tree->find($blockId);
            $extensions = $block->extensions;
            unset($extensions[BlockLock::NAMESPACE]);

            if ($lock->isLocked())
            {
                $extensions[BlockLock::NAMESPACE] = ['move' => $lock->move, 'remove' => $lock->remove];
            }

            $tree = $tree->replace($blockId, new Block(
                $block->id,
                $block->type,
                $block->typeVersion,
                $block->settings,
                $extensions,
                $block->bindings,
                $block->children,
            ));
            $affected[] = $blockId;
        }

        return EditResult::accepted($document->withTree($tree), $affected);
    }

    public function transform(Document $document, string $blockId, BlockName $to): EditResult
    {
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        $from = $this->types->get($block->type);
        $target = $this->types->get($to);

        if ($from === null || $target === null)
        {
            return EditResult::refused($this->unknownType($from === null ? $block->type : $to, $blockId));
        }

        $transform = $from->transformTo($to);

        if ($transform === null)
        {
            return EditResult::refused(new EditRefusal(
                EditRefusalKind::NoTransform,
                sprintf(
                    'A %s block cannot be turned into a %s block.',
                    $from->catalogue->title,
                    $target->catalogue->title,
                ),
                $blockId,
            ));
        }

        $settings = [];

        foreach ($transform->settings as $setting => $source)
        {
            if (array_key_exists($source, $block->settings))
            {
                $settings[$setting] = $block->settings[$source];
            }
        }

        $extensions = array_filter(
            $block->extensions,
            fn (string $namespace): bool => $this->decorators->get($namespace)?->appliesTo($to) === true,
            ARRAY_FILTER_USE_KEY,
        );
        $replacement = new Block(
            $block->id,
            $to,
            $target->version,
            $settings,
            $extensions,
            $this->carriedBindings($block, $transform->settings, $extensions),
            $block->children,
        );
        $refusals = [];
        $vetted = $this->vetted(
            $replacement,
            $this->parentType($document->tree, $document->tree->positionOf($blockId)),
            $refusals,
        );

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return $this->replaced($document, $vetted);
    }

    /**
     * Moves a block's bindings to the settings that take over from the ones they fed.
     *
     * A binding on a setting the transform does not carry over is dropped with the setting. A binding on a
     * decorator's setting stays when that decorator's settings stay.
     *
     * @param array<string, string> $map
     *   The transform's map, new setting to old.
     * @param array<string, mixed> $extensions
     *   The decorator settings the new block keeps.
     * @return array<string, Binding>
     */
    private function carriedBindings(Block $block, array $map, array $extensions): array
    {
        $bindings = [];

        foreach ($block->bindings as $key => $binding)
        {
            $path = SettingPath::fromString((string) $key);

            if ($path->namespace() !== null)
            {
                if (array_key_exists($path->namespace(), $extensions))
                {
                    $bindings[(string) $key] = $binding;
                }

                continue;
            }

            [$first, $rest] = [$path->segments()[0], array_slice($path->segments(), 1)];

            foreach ($map as $setting => $source)
            {
                if ($source === $first)
                {
                    $bindings[implode('.', [(string) $setting, ...$rest])] = $binding;
                }
            }
        }

        return $bindings;
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

        $settings = $this->cleaned($settings, $type->settingsSchema);
        $changed = $block->withSettings($settings)->withTypeVersion($type->version);
        $refusals = $this->settingsRefusals($settings, $type->settingsSchema, $blockId);
        $refusals = $refusals !== [] ? $refusals : $this->stepRefusals($changed, $type, $blockId);

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return $this->replaced($document, $changed);
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
        $settings = $decorator === null ? $settings : $this->cleaned($settings, $decorator->settingsSchema);
        $changed = $block->withExtension($namespace, $settings);
        $type = $this->types->get($block->type);
        $refusals = $this->extensionRefusals($block->type, $blockId, $namespace, $settings);

        if ($refusals === [] && $type !== null)
        {
            $refusals = $this->stepRefusals($changed, $type, $blockId);
        }

        if ($refusals !== [])
        {
            return EditResult::refused(...$refusals);
        }

        return $this->replaced($document, $changed);
    }

    public function setBinding(Document $document, string $blockId, SettingPath $path, Binding $binding): EditResult
    {
        $block = $document->tree->find($blockId);

        if ($block === null)
        {
            return EditResult::refused($this->unknownBlock($blockId));
        }

        $changed = $block->withBinding($path, $binding);
        $type = $this->types->get($block->type);
        $refusals = $this->bindingRefusals($block->type, $blockId, $path, $binding);

        if ($refusals === [] && $type !== null)
        {
            $refusals = $this->stepRefusals($changed, $type, $blockId);
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
    private function schemaFor(BlockName $type, SettingPath $path): ?array
    {
        if ($path->namespace() === null)
        {
            return $this->types->get($type)?->settingsSchema;
        }

        $decorator = $this->decorators->get($path->namespace());

        return $decorator !== null && $decorator->appliesTo($type) ? $decorator->settingsSchema : null;
    }

    /**
     * Checks a block about to be placed, then its descendants, and readies it for the document.
     *
     * A pasted block is held to everything the single-block operations enforce, so an accepted paste resolves as
     * cleanly as the same blocks built one edit at a time. Settings saved under an older version of the type are
     * migrated first, as resolving would, and the block is stamped with the current version.
     *
     * @param BlockName|null $parent
     *   The type the block would sit inside; null at the document root.
     * @param list<EditRefusal> $refusals
     *   Collects every reason found, block by block in tree order.
     *
     * @since 0.1.0
     */
    private function vetted(Block $block, ?BlockName $parent, array &$refusals): Block
    {
        $type = $this->types->get($block->type);
        $before = count($refusals);

        if ($type === null)
        {
            $refusals[] = $this->unknownType($block->type, $block->id);
        }
        else
        {
            $refusals = [...$refusals, ...$this->placementRefusals($block->type, $parent, $block->id)];
            $settings = $this->migrated($block, $type, $refusals);

            if ($settings !== null)
            {
                $settings = $this->cleaned($settings, $type->settingsSchema);
                $refusals = [...$refusals, ...$this->settingsRefusals($settings, $type->settingsSchema, $block->id)];
                $block = $block->withSettings($settings)->withTypeVersion($type->version);
            }

            foreach ($block->extensions as $namespace => $extension)
            {
                $decorator = $this->decorators->get((string) $namespace);

                if ($decorator !== null)
                {
                    $extension = $this->cleaned($extension, $decorator->settingsSchema);
                    $block = $block->withExtension((string) $namespace, $extension);
                }

                $refusals = [
                    ...$refusals,
                    ...$this->extensionRefusals($block->type, $block->id, (string) $namespace, $extension),
                ];
            }

            foreach ($block->bindings as $path => $binding)
            {
                $refusals = [...$refusals, ...$this->storedBindingRefusals($block, (string) $path, $binding)];
            }

            if (count($refusals) === $before)
            {
                $refusals = [...$refusals, ...$this->stepRefusals($block, $type, $block->id)];
            }
        }

        $children = [];

        foreach ($block->children as $child)
        {
            $children[] = $this->vetted($child, $block->type, $refusals);
        }

        return $block->withChildren(...$children);
    }

    /**
     * Asks the steps that check blocks while resolving whether they would report this one.
     *
     * Packages add checks the schema cannot express, such as "this collection exists". Running them here keeps
     * the promise that a document the editor accepts resolves cleanly. A step that fails is passed over: it will
     * be reported when the document is resolved, and must not make every edit impossible.
     *
     * @param Block $block
     *   The block as it would be stored.
     * @param string|null $blockId
     *   The id refusals name; null for a block that does not exist yet.
     *
     * @return list<EditRefusal>
     *
     * @since 0.1.0
     */
    private function stepRefusals(Block $block, BlockType $type, ?string $blockId): array
    {
        $settings = $this->validator->withDefaults($block->settings, $type->settingsSchema);
        $refusals = [];

        foreach ($this->steps->for(ValidateStepInterface::class) as $step)
        {
            try
            {
                $issues = $step->validate($block, $type, $settings, Context::empty(), Mode::Admin);
            }
            catch (Throwable)
            {
                continue;
            }

            foreach ($issues as $issue)
            {
                $refusals[] = new EditRefusal(
                    EditRefusalKind::InvalidSettings,
                    $issue->message,
                    $blockId,
                    $issue->path,
                );
            }
        }

        return $refusals;
    }

    /**
     * Cleans every formatted-text value in settings, wherever the schema declares one, so only clean text is stored.
     *
     * @param array<array-key, mixed> $values
     * @param array<string, mixed> $schema
     *
     * @return array<array-key, mixed>
     *
     * @since 0.1.0
     */
    private function cleaned(array $values, array $schema): array
    {
        if ($this->richText === null)
        {
            return $values;
        }

        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $items = is_array($schema['items'] ?? null) ? $schema['items'] : null;
        $extra = is_array($schema['additionalProperties'] ?? null) ? $schema['additionalProperties'] : null;

        foreach ($values as $key => $value)
        {
            $child = $properties[$key] ?? (array_is_list($values) ? $items : $extra);

            if (!is_array($child))
            {
                continue;
            }

            if (is_string($value) && ($child['x-control'] ?? null) === 'rich-text')
            {
                $values[$key] = $this->richText->clean($value);
            }
            elseif (is_array($value))
            {
                $values[$key] = $this->cleaned($value, $child);
            }
        }

        return $values;
    }

    /**
     * Lists the ids of a block and everything inside it, in tree order, repeats included.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function subtreeIds(Block $block): array
    {
        $ids = [$block->id];

        foreach ($block->children as $child)
        {
            $ids = [...$ids, ...$this->subtreeIds($child)];
        }

        return $ids;
    }

    /**
     * Brings a block's settings to the type's current version through the registered migrations.
     *
     * @param list<EditRefusal> $refusals
     *
     * @return array<string, mixed>|null
     *   Null when no chain of migrations leads from the block's version to the current one, or one of them fails.
     *
     * @since 0.1.0
     */
    private function migrated(Block $block, BlockType $type, array &$refusals): ?array
    {
        $chain = $this->migrations->chain($block->type, $block->typeVersion, $type->version);
        $settings = $block->settings;

        try
        {
            if ($chain === null)
            {
                throw new RuntimeException('no migration path');
            }

            foreach ($chain as $migration)
            {
                $settings = $migration->migrate($settings);
            }
        }
        catch (Throwable)
        {
            $refusals[] = new EditRefusal(EditRefusalKind::UnsupportedVersion, sprintf(
                'Settings of "%s" cannot be migrated from version %d to %d.',
                $block->type->toString(),
                $block->typeVersion,
                $type->version,
            ), $block->id);

            return null;
        }

        return $settings;
    }

    /**
     * Checks one decorator's settings on a block: the decorator must apply to the type and the settings fit it.
     *
     * @param array<string, mixed> $settings
     *
     * @return list<EditRefusal>
     *
     * @since 0.1.0
     */
    private function extensionRefusals(BlockName $type, string $blockId, string $namespace, array $settings): array
    {
        $decorator = $this->decorators->get($namespace);

        if ($decorator === null || !$decorator->appliesTo($type))
        {
            return [new EditRefusal(
                EditRefusalKind::UnknownDecorator,
                sprintf('No decorator "%s" applies to block type "%s".', $namespace, $type->toString()),
                $blockId,
            )];
        }

        return $this->settingsRefusals($settings, $decorator->settingsSchema, $blockId, $namespace);
    }

    /**
     * Checks a binding a block already carries, whose path is stored as text and may not even parse.
     *
     * @return list<EditRefusal>
     *
     * @since 0.1.0
     */
    private function storedBindingRefusals(Block $block, string $path, Binding $binding): array
    {
        try
        {
            $parsed = SettingPath::fromString($path);
        }
        catch (InvalidSettingPathException $e)
        {
            return [new EditRefusal(EditRefusalKind::InvalidBinding, $e->getMessage(), $block->id, $path)];
        }

        return $this->bindingRefusals($block->type, $block->id, $parsed, $binding);
    }

    /**
     * Checks a binding the way resolving will: the setting, its privacy, the source, its arguments, the fallback.
     *
     * @return list<EditRefusal>
     *
     * @since 0.1.0
     */
    private function bindingRefusals(BlockName $type, string $blockId, SettingPath $path, Binding $binding): array
    {
        $schema = $this->schemaFor($type, $path);
        $setting = $schema === null ? null : $this->validator->subschema($schema, $path->segments());

        if ($schema === null || $setting === null)
        {
            return [$this->invalidBinding(
                sprintf('"%s" is not a setting block "%s" declares.', $path->toString(), $blockId),
                $blockId,
                $path,
            )];
        }

        if (in_array($path->segments()[0], $this->validator->privateProperties($schema), true))
        {
            return [new EditRefusal(
                EditRefusalKind::PrivateBinding,
                sprintf('"%s" is private and cannot be bound to a data source.', $path->toString()),
                $blockId,
                $path->toString(),
            )];
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
            // Judged as given, without defaults: resolving hands the source exactly these arguments.
            foreach ($this->validator->validate($binding->arguments, $source->argumentsSchema())->errors() as $error)
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
     * Refuses a position inside a block whose type is not registered: nothing is known about what it may hold.
     *
     * @since 0.1.0
     */
    private function parentRefusal(BlockTree $tree, Position $position): ?EditRefusal
    {
        $parent = $position->parentId === null ? null : $tree->find($position->parentId);

        if ($parent === null || $this->types->has($parent->type))
        {
            return null;
        }

        return $this->unknownType($parent->type, $parent->id);
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
