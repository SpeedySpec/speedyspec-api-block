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

    /**
     * Reads the title as written, not as themes display it: the display filters turn quotes into entities and add
     * "Private:" prefixes, which would then be escaped again and shown to visitors as code.
     */
    public function title(int $id): string
    {
        $title = wp_strip_all_tags((string) get_post_field('post_title', $id, 'raw'));

        return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
