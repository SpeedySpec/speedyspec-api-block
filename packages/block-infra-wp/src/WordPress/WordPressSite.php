<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\WordPress;

use SpeedySpec\Block\Infra\WP\Ports\WpSiteInterface;

/**
 * Reads where a post sits from WordPress; the only site-map code that calls WordPress.
 *
 * @since 0.1.0
 */
class WordPressSite implements WpSiteInterface
{
    public function permalink(int $id): ?string
    {
        $url = get_permalink($id);

        return is_string($url) ? $url : null;
    }

    public function title(int $id): string
    {
        return wp_strip_all_tags((string) get_the_title($id));
    }

    public function ancestors(int $id): array
    {
        return array_reverse(array_map('intval', get_post_ancestors($id)));
    }

    public function homeUrl(): string
    {
        return home_url('/');
    }

    public function siteName(): string
    {
        return (string) get_bloginfo('name');
    }
}
