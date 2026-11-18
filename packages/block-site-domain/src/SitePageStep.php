<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site\Domain;

use SpeedySpec\Block\Domain\Contracts\DocumentStepInterface;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\HeadingAnchor;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Looks at the whole page once, before any block is drawn, and tells the site blocks what only the page knows.
 *
 * It lists the page's headings and gives each a unique id, which headings then use, so a table of contents always
 * links to the right one. It also passes on where the host says the page sits: its address, its trail and where
 * search goes. Headings inside a repeating block appear once per item, so they are left out; included documents
 * are drawn separately and are never part of this page's tree.
 *
 * @since 0.1.0
 */
class SitePageStep implements DocumentStepInterface
{
    public const string HEADINGS = 'core/headings';

    public const string HEADING_IDS = 'core/heading-ids';

    public const string PAGE_URL = 'core/page-url';

    public const string BREADCRUMBS = 'core/breadcrumbs';

    public const string SEARCH = 'core/search';

    /**
     * The extension visibility rules are stored under, by name, so this package needs no other to read it.
     */
    private const string VISIBILITY = 'core/visibility';

    /**
     * The pattern a heading's anchor must match, as its definition has it.
     */
    private const string ANCHOR = '/^([a-z][a-z0-9-]{0,63})?$/D';

    /**
     * @param SiteMapInterface|null $siteMap
     *   Where the host says pages sit; null on a host that says nothing.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly ?SiteMapInterface $siteMap = null)
    {
    }

    public function context(Document $document, Context $context, Mode $mode): Context
    {
        // An included document, such as a header, is drawn with the page's context: the page's address, trail and
        // contents stay the page's, and its headings only take ids the page has not used.
        if ($context->has(self::PAGE_URL))
        {
            $outer = $context->has(self::HEADING_IDS) && is_array($context->get(self::HEADING_IDS))
                ? $context->get(self::HEADING_IDS)
                : [];
            $inner = $this->headings($document->tree->roots(), array_values($outer));

            return $context->with(self::HEADING_IDS, [...$outer, ...array_column($inner, 'id', 'block')]);
        }

        $headings = $this->headings($document->tree->roots());
        $search = $this->siteMap?->search();

        return $context
            ->with(self::HEADINGS, array_values(array_map(
                static fn (array $heading): array => [
                    'level' => $heading['level'],
                    'text' => $heading['text'],
                    'id' => $heading['id'],
                ],
                array_filter($headings, static fn (array $heading): bool => $heading['text'] !== ''),
            )))
            ->with(self::HEADING_IDS, array_column($headings, 'id', 'block'))
            ->with(self::PAGE_URL, $this->siteMap?->url($document->id) ?? '')
            ->with(self::BREADCRUMBS, array_map(
                static fn (Crumb $crumb): array => ['label' => $crumb->label, 'url' => $crumb->url],
                $this->siteMap?->trail($document->id) ?? [],
            ))
            ->with(self::SEARCH, $search === null ? null : ['url' => $search->url, 'parameter' => $search->parameter]);
    }

    /**
     * Lists the page's headings in order, each with a unique id; typed anchors are settled first, so they keep their
     * exact value and a derived id that clashes is the one numbered.
     *
     * @param list<Block> $roots
     * @param list<string> $used
     *   Ids the page around this document already uses.
     * @return list<array{block: string, level: int, text: string, id: string}>
     */
    private function headings(array $roots, array $used = []): array
    {
        $found = [];
        $this->collect($roots, $found);
        $typed = array_keys(array_filter($found, static fn (array $heading): bool => $heading['typed']));
        $derived = array_keys(array_filter($found, static fn (array $heading): bool => !$heading['typed']));
        $order = [...$typed, ...$derived];
        $ids = HeadingAnchor::unique(array_map(static fn (int $at): string => $found[$at]['wanted'], $order), $used);

        foreach ($order as $position => $at)
        {
            $found[$at]['id'] = $ids[$position];
        }

        return array_map(static fn (array $heading): array => [
            'block' => $heading['block'],
            'level' => $heading['level'],
            'text' => $heading['text'],
            'id' => $heading['id'],
        ], $found);
    }

    /**
     * @param list<Block> $blocks
     * @param list<array<string, mixed>> $found
     */
    private function collect(array $blocks, array &$found): void
    {
        foreach ($blocks as $block)
        {
            $type = $block->type->toString();

            // Rules may hide this block and all it holds from some visitors; its headings are never listed, so a
            // table of contents cannot show what an author hid.
            if (($block->extensions[self::VISIBILITY]['groups'] ?? []) !== [])
            {
                continue;
            }

            if ($type === 'core/heading' && $this->listable($block))
            {
                $text = is_string($block->settings['text'] ?? null) ? $block->settings['text'] : '';
                $anchor = is_string($block->settings['anchor'] ?? null) ? $block->settings['anchor'] : '';
                $found[] = [
                    'block' => $block->id,
                    'level' => is_int($block->settings['level'] ?? null) ? $block->settings['level'] : 2,
                    'text' => trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    'typed' => $anchor !== '',
                    'wanted' => $anchor !== '' ? $anchor : HeadingAnchor::fromText($text),
                    'id' => '',
                ];
            }

            if ($type !== 'core/repeat')
            {
                $this->collect($block->children, $found);
            }
        }
    }

    /**
     * Tells whether a heading will be drawn with the text and anchor stored for it.
     *
     * A heading whose stored settings break its definition is not drawn, so it must neither be listed nor claim an
     * id; one whose text comes from a binding is only known once it is drawn, so it makes its own id.
     */
    private function listable(Block $block): bool
    {
        $level = $block->settings['level'] ?? 2;
        $text = $block->settings['text'] ?? '';
        $anchor = $block->settings['anchor'] ?? '';

        return is_int($level) && $level >= 1 && $level <= 6
            && is_string($text)
            && is_string($anchor) && preg_match(self::ANCHOR, $anchor) === 1
            && !isset($block->bindings['text']);
    }
}
