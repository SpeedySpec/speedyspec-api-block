<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\EditResult;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

/**
 * The editing operations every builder shares, so documents stay valid whichever builder made them.
 *
 * Each operation takes the current document and answers with a new one or with every reason it was refused; the
 * document passed in is never changed. Nothing here stores a document: saving is the caller's step.
 *
 * A refusal is for a request that cannot be honoured. A collaborator that is broken, such as an id generator that
 * only produces ids already in use, is an exception instead.
 *
 * @since 0.1.0
 */
interface DocumentEditorInterface
{
    /**
     * Adds a new, childless block of a registered type.
     *
     * @param array<string, mixed> $settings
     *   Stored as given; the type's defaults are not written in.
     *
     * @since 0.1.0
     */
    public function insert(Document $document, BlockName $type, Position $position, array $settings = []): EditResult;

    /**
     * Adds existing blocks, such as pasted ones, giving every block a new id.
     *
     * Nothing is added unless every block is valid where it would land, with its settings, decorator settings and
     * bindings held to the same checks as the single-block operations. Settings saved under an older version of a
     * type are migrated; a block that cannot be brought to the current version is refused.
     *
     * @param list<Block> $blocks
     *   Subtrees placed in order, starting at the position.
     *
     * @since 0.1.0
     */
    public function insertBlocks(Document $document, array $blocks, Position $position): EditResult;

    /**
     * Replaces every block of the document with the given ones, keeping their ids.
     *
     * This is what undo and redo are built on: a builder keeps the states it was given and hands one back. The
     * state is checked like any other edit, since it arrives from outside and block types may have changed since
     * it was taken. Unlike a paste, ids are kept, so a block is the same block before and after.
     *
     * @param list<Block> $blocks
     *   The document's root blocks. Every id in them must be different.
     *
     * @since 0.1.0
     */
    public function restore(Document $document, array $blocks): EditResult;

    /**
     * Adds a copy of a template's blocks; the template itself is unchanged and can be inserted again.
     *
     * @since 0.1.0
     */
    public function insertTemplate(Document $document, BlockTemplate $template, Position $position): EditResult;

    /**
     * Moves a block, with its descendants, to another place in the same document.
     *
     * @param Position $position
     *   Read against the document with the block already taken out. Among siblings `a, b, c`, moving `a` to the
     *   end is index 2, not 3, because only `b, c` remain when the position is read.
     *
     * @since 0.1.0
     */
    public function move(Document $document, string $blockId, Position $position): EditResult;

    /**
     * Removes a block and its descendants.
     *
     * @since 0.1.0
     */
    public function remove(Document $document, string $blockId): EditResult;

    /**
     * Removes several blocks, with their descendants, as one edit.
     *
     * A builder removing a selection uses this so the removal is one step to undo. A listed block inside another
     * listed block simply goes with it. Nothing is removed unless every id is in the document.
     *
     * @param list<string> $blockIds
     *   At least one id.
     *
     * @since 0.1.0
     */
    public function removeBlocks(Document $document, array $blockIds): EditResult;

    /**
     * Places a copy of a block, with new ids throughout, directly after it.
     *
     * @since 0.1.0
     */
    public function duplicate(Document $document, string $blockId): EditResult;

    /**
     * Replaces all of a block's own settings.
     *
     * The block is marked as being at its type's current version, since the new settings are checked against the
     * current schema. Its bindings and decorator settings are kept as they are.
     *
     * @param array<string, mixed> $settings
     *
     * @since 0.1.0
     */
    public function updateSettings(Document $document, string $blockId, array $settings): EditResult;

    /**
     * Replaces the settings a decorator keeps on a block.
     *
     * @param string $namespace
     *   The decorator's namespace.
     * @param array<string, mixed> $settings
     *
     * @since 0.1.0
     */
    public function updateExtension(
        Document $document,
        string $blockId,
        string $namespace,
        array $settings,
    ): EditResult;

    /**
     * Binds one of a block's settings to a data source.
     *
     * @since 0.1.0
     */
    public function setBinding(Document $document, string $blockId, SettingPath $path, Binding $binding): EditResult;

    /**
     * Removes the binding on one of a block's settings, if there is one.
     *
     * @since 0.1.0
     */
    public function clearBinding(Document $document, string $blockId, SettingPath $path): EditResult;
}
