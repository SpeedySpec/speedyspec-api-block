<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\WordPress;

use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Infra\WP\Ports\WpPostsInterface;

/**
 * Reads and writes posts through WordPress's own functions.
 *
 * A document id is a post id. Saving under an id WordPress does not have creates a post and cannot give it that
 * id, so new documents are created in WordPress and opened here by the id it assigns.
 *
 * @since 0.1.0
 */
class WordPressPosts implements WpPostsInterface
{
    /**
     * @param list<string> $postTypes
     *   The post types listed as documents.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly array $postTypes = ['post', 'page'])
    {
    }

    public function content(string $id): ?string
    {
        $post = ctype_digit($id) ? \get_post((int) $id) : null;

        return $post === null ? null : (string) $post->post_content;
    }

    public function kind(string $id): string
    {
        return (string) \get_post_type((int) $id);
    }

    public function save(string $id, string $kind, string $content): void
    {
        if (!ctype_digit($id) || \get_post((int) $id) === null)
        {
            throw new StorageException(sprintf('There is no post "%s" to save into.', $id));
        }

        // WordPress expects slashed input here and strips one level of slashes itself.
        $result = \wp_update_post(['ID' => (int) $id, 'post_content' => \wp_slash($content)], true);

        if (\is_wp_error($result))
        {
            throw new StorageException(sprintf('Post "%s" could not be saved.', $id));
        }
    }

    public function delete(string $id): void
    {
        if (ctype_digit($id))
        {
            \wp_trash_post((int) $id);
        }
    }

    public function ids(): array
    {
        return array_map(strval(...), \get_posts([
            'post_type' => $this->postTypes,
            'post_status' => 'any',
            'fields' => 'ids',
            'numberposts' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]));
    }
}
