<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

if (!function_exists('block'))
{
    /**
     * Builds a block for tests; named children arguments are collected under string keys, so they are re-indexed.
     */
    function block(string $id, string $type = 'acme/box', array $settings = [], Block ...$children): Block
    {
        return new Block($id, BlockName::fromString($type), 1, $settings, [], [], array_values($children));
    }
}

if (!function_exists('doc'))
{
    /**
     * Builds a page document with id "d" for tests.
     */
    function doc(Block ...$roots): Document
    {
        return new Document('d', 'page', new BlockTree(...$roots));
    }
}
