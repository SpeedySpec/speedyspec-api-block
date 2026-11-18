<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Exceptions\UnavailableBlockException;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Says whether a menu link points at the page being drawn, and how deep in the menu it sits.
 *
 * A link is current when it names the same path as the page, whatever its trailing slash, query or fragment. Menus
 * go two submenu levels deep at most, which the parent and child rules cannot count, so a link counts for itself.
 *
 * @since 0.1.0
 */
class NavigationLinkBehaviour implements BlockBehaviourInterface
{
    public const string DEPTH = 'core/nav-depth';

    private const int MAX_DEPTH = 3;

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $above = $context->has(self::DEPTH) ? $context->get(self::DEPTH) : 0;
        $depth = (is_int($above) ? $above : 0) + 1;

        if ($depth > self::MAX_DEPTH)
        {
            throw new UnavailableBlockException(IssueKind::DisallowedChild, 'Menus go two levels deep at most.');
        }

        $url = is_string($settings['url'] ?? null) ? trim($settings['url']) : '';
        $page = $context->has(SitePageStep::PAGE_URL) ? $context->get(SitePageStep::PAGE_URL) : '';

        return new BlockView([
            'label' => is_string($settings['label'] ?? null) ? $settings['label'] : '',
            'url' => $url,
            'newTab' => ($settings['newTab'] ?? false) === true,
            'current' => is_string($page) && self::samePage($url, $page),
            'depth' => $depth,
        ]);
    }

    /**
     * Tells whether two addresses name the same page: the same path, on the same site when both name a site.
     *
     * @since 0.1.0
     */
    public static function samePage(string $link, string $page): bool
    {
        if ($link === '' || $page === '')
        {
            return false;
        }

        $one = parse_url($link);
        $two = parse_url($page);

        if (!is_array($one) || !is_array($two) || !isset($one['path']) || !isset($two['path']))
        {
            return false;
        }

        if (isset($one['host'], $two['host']) && strtolower($one['host']) !== strtolower($two['host']))
        {
            return false;
        }

        $path = static fn (string $value): string => rtrim($value, '/') === '' ? '/' : rtrim($value, '/');

        return $path($one['path']) === $path($two['path']);
    }
}
