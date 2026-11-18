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
        $headings = $this->headings($document->tree->roots());
        $search = $this->siteMap?->search();

        return $context
            ->with(self::HEADINGS, array_map(
                static fn (array $heading): array => [
                    'level' => $heading['level'],
                    'text' => $heading['text'],
                    'id' => $heading['id'],
                ],
                $headings,
            ))
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
     * @return list<array{block: string, level: int, text: string, id: string}>
     */
    private function headings(array $roots): array
    {
        $found = [];
        $this->collect($roots, $found);
        $typed = array_keys(array_filter($found, static fn (array $heading): bool => $heading['typed']));
        $derived = array_keys(array_filter($found, static fn (array $heading): bool => !$heading['typed']));
        $order = [...$typed, ...$derived];
        $ids = HeadingAnchor::unique(array_map(static fn (int $at): string => $found[$at]['wanted'], $order));

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

            if ($type === 'core/heading')
            {
                $text = is_string($block->settings['text'] ?? null) ? $block->settings['text'] : '';
                $anchor = is_string($block->settings['anchor'] ?? null) ? trim($block->settings['anchor']) : '';
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
}
