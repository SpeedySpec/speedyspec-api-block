<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\Ports;

/**
 * What the adapter needs from WordPress's posts: their block markup, by id.
 *
 * It is a port so the adapter can be tested, and used, without WordPress loaded.
 *
 * @since 0.1.0
 */
interface WpPostsInterface
{
    /**
     * Reads a post's content.
     *
     * @return string|null
     *   Null when there is no such post.
     *
     * @since 0.1.0
     */
    public function content(string $id): ?string;

    /**
     * Names what kind of document a post is, usually its post type.
     *
     * @since 0.1.0
     */
    public function kind(string $id): string;

    /**
     * Writes a post's content, creating the post when the id is new.
     *
     * @since 0.1.0
     */
    public function save(string $id, string $kind, string $content): void;

    /**
     * Removes a post; an unknown id is not an error.
     *
     * @since 0.1.0
     */
    public function delete(string $id): void;

    /**
     * Lists the ids of the posts that hold block documents.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    public function ids(): array;
}
