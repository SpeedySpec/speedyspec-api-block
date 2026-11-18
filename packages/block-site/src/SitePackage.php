<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site;

use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Site\Domain\SiteTypesPackage;

/**
 * Draws the site blocks that {@link SiteTypesPackage} defines, with one stylesheet and no script.
 *
 * @since 0.1.0
 */
class SitePackage implements BlockPackageInterface
{
    public const string STYLESHEET = 'core/site';

    private const array BLOCKS = ['navigation', 'navigation-link', 'table-of-contents', 'breadcrumbs', 'search'];

    public function name(): string
    {
        return 'core/site';
    }

    public function title(): string
    {
        return 'Site, drawn';
    }

    public function description(): string
    {
        return 'The standard way to draw menus, tables of contents, breadcrumbs and search.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $registrar->asset(Asset::inline(self::STYLESHEET, AssetKind::Style, SiteStylesheet::CSS));

        foreach (self::BLOCKS as $block)
        {
            $registrar->output(BlockName::fromString('core/' . $block), new SiteOutput($block));
        }
    }
}
