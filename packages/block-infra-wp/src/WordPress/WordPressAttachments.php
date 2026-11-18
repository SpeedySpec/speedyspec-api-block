<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\WordPress;

use RuntimeException;
use SpeedySpec\Block\Infra\WP\Ports\WpAttachmentsInterface;
use WP_Query;

/**
 * Reads and adds WordPress attachments; the only media code that calls WordPress.
 *
 * @since 0.1.0
 */
class WordPressAttachments implements WpAttachmentsInterface
{
    public function attachment(int $id): ?array
    {
        $post = get_post($id);

        if ($post === null || $post->post_type !== 'attachment')
        {
            return null;
        }

        $metadata = wp_get_attachment_metadata($id);
        $metadata = is_array($metadata) ? $metadata : [];
        $sizes = [];

        foreach (array_keys(is_array($metadata['sizes'] ?? null) ? $metadata['sizes'] : []) as $name)
        {
            $source = wp_get_attachment_image_src($id, (string) $name);

            if (is_array($source))
            {
                $sizes[] = [
                    'name' => (string) $name,
                    'url' => $source[0],
                    'width' => $source[1],
                    'height' => $source[2],
                ];
            }
        }

        return [
            'id' => $id,
            'url' => (string) wp_get_attachment_url($id),
            'mimeType' => (string) $post->post_mime_type,
            'title' => (string) $post->post_title,
            'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            'width' => isset($metadata['width']) ? (int) $metadata['width'] : null,
            'height' => isset($metadata['height']) ? (int) $metadata['height'] : null,
            'sizes' => $sizes,
        ];
    }

    public function search(string $search, ?string $mimePrefix, int $page, int $perPage): array
    {
        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => $mimePrefix === null ? '' : rtrim($mimePrefix, '/'),
            's' => $search,
            'orderby' => 'date',
            'order' => 'DESC',
            'paged' => $page,
            'posts_per_page' => $perPage,
            'fields' => 'ids',
        ]);

        return ['ids' => array_map('intval', $query->posts), 'total' => (int) $query->found_posts];
    }

    public function insert(string $name, string $mimeType, string $bytes): int
    {
        $stored = wp_upload_bits(sanitize_file_name($name), null, $bytes);

        if (!empty($stored['error']))
        {
            throw new RuntimeException('WordPress could not store the uploaded file.');
        }

        $id = wp_insert_attachment([
            'post_mime_type' => $mimeType,
            'post_title' => sanitize_text_field(pathinfo($name, PATHINFO_FILENAME)),
            'post_status' => 'inherit',
        ], $stored['file'], 0, true);

        if (is_wp_error($id))
        {
            throw new RuntimeException('WordPress could not add the uploaded file to the media library.');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $stored['file']));

        return (int) $id;
    }
}
