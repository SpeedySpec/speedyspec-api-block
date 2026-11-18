<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions\Domain;

/**
 * The services a social link may point to, and which links each one accepts.
 *
 * A link named after a service must go to that service's own site, so a page cannot show "Instagram" over a link to
 * somewhere else. Services whose addresses vary, such as a website or a Mastodon server, take any https link.
 *
 * @since 0.1.0
 */
class SocialServices
{
    /**
     * Every service, in the order a builder offers them.
     */
    public const array NAMES = [
        'website', 'email', 'rss', 'mastodon', 'bluesky', 'facebook', 'instagram', 'linkedin', 'x', 'youtube',
        'tiktok', 'github', 'threads', 'pinterest',
    ];

    private const array TITLES = [
        'website' => 'Website',
        'email' => 'Email',
        'rss' => 'RSS feed',
        'mastodon' => 'Mastodon',
        'bluesky' => 'Bluesky',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'github' => 'GitHub',
        'threads' => 'Threads',
        'pinterest' => 'Pinterest',
    ];

    /**
     * The hosts each service's links may go to; a service not listed takes any https link, except email.
     */
    private const array HOSTS = [
        'bluesky' => ['bsky.app'],
        'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
        'instagram' => ['instagram.com', 'www.instagram.com'],
        'linkedin' => ['linkedin.com', 'www.linkedin.com', 'm.linkedin.com'],
        'x' => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com', 'mobile.twitter.com'],
        'youtube' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
        'tiktok' => ['tiktok.com', 'www.tiktok.com'],
        'github' => ['github.com', 'www.github.com'],
        'threads' => ['threads.net', 'www.threads.net'],
        'pinterest' => ['pinterest.com', 'www.pinterest.com'],
    ];

    /**
     * Names a service for people.
     *
     * @since 0.1.0
     */
    public static function title(string $service): string
    {
        return self::TITLES[$service] ?? 'Link';
    }

    /**
     * Tells whether a link may be shown under a service's name.
     *
     * @since 0.1.0
     */
    public static function accepts(string $service, string $url): bool
    {
        if (!in_array($service, self::NAMES, true) || preg_match('/[\s\x00-\x1F]/', $url) === 1)
        {
            return false;
        }

        if ($service === 'email')
        {
            return preg_match('/^mailto:[^@\s]+@[^@\s]+$/i', $url) === 1;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']))
        {
            return false;
        }

        $hosts = self::HOSTS[$service] ?? null;

        return $hosts === null || in_array(strtolower($parts['host']), $hosts, true);
    }
}
