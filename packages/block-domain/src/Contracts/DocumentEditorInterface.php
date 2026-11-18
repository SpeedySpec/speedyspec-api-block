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
     * Nothing is added unless every block is valid where it would land.
     *
     * @param list<Block> $blocks
     *   Subtrees placed in order, starting at the position.
     *
     * @since 0.1.0
     */
    public function insertBlocks(Document $document, array $blocks, Position $position): EditResult;

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
     *   Read against the document with the block already taken out.
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
     * Places a copy of a block, with new ids throughout, directly after it.
     *
     * @since 0.1.0
     */
    public function duplicate(Document $document, string $blockId): EditResult;

    /**
     * Replaces all of a block's own settings.
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
