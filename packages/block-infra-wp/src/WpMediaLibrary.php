<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use RuntimeException;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaPage;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Domain\ValueObjects\MediaSize;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;
use SpeedySpec\Block\Infra\WP\Ports\WpAttachmentsInterface;

/**
 * Uses WordPress attachments as the media library, so blocks show the files a site already has.
 *
 * Ids are attachment ids written as text; WordPress makes the image sizes.
 *
 * @since 0.1.0
 */
class WpMediaLibrary implements MediaLibraryInterface
{
    private const array PREFIXES = [
        'image' => 'image/',
        'video' => 'video/',
        'audio' => 'audio/',
        'file' => 'application/',
    ];

    public function __construct(private readonly WpAttachmentsInterface $attachments)
    {
    }

    public function find(string $id): ?MediaItem
    {
        // Only a positive whole number written plainly can be an attachment id; anything else is not looked up.
        if (preg_match('/^[1-9][0-9]{0,18}$/', $id) !== 1)
        {
            return null;
        }

        $attachment = $this->attachments->attachment((int) $id);

        return $attachment === null ? null : $this->item($attachment);
    }

    public function search(MediaQuery $query): MediaPage
    {
        $found = $this->attachments->search(
            $query->search,
            $query->kind === null ? null : self::PREFIXES[$query->kind->value],
            $query->page,
            $query->perPage,
        );
        $items = [];

        foreach ($found['ids'] as $id)
        {
            $attachment = $this->attachments->attachment($id);

            if ($attachment !== null)
            {
                $items[] = $this->item($attachment);
            }
        }

        return new MediaPage($items, $found['total']);
    }

    public function upload(MediaUpload $upload): MediaItem
    {
        // WordPress stores and serves a file by its extension, so the name it is given carries the type found in the
        // content, never the one the person typed.
        $id = $this->attachments->insert($upload->storedName(), $upload->mimeType, $upload->bytes);
        $attachment = $this->attachments->attachment($id);

        if ($attachment === null)
        {
            throw new RuntimeException('WordPress did not keep the uploaded file.');
        }

        return $this->item($attachment);
    }

    /**
     * @param array<string, mixed> $attachment
     */
    private function item(array $attachment): MediaItem
    {
        $mimeType = (string) ($attachment['mimeType'] ?? 'application/octet-stream');
        $sizes = [];

        foreach (is_array($attachment['sizes'] ?? null) ? $attachment['sizes'] : [] as $size)
        {
            if (is_array($size) && is_string($size['name'] ?? null) && is_string($size['url'] ?? null))
            {
                $sizes[] = new MediaSize(
                    $size['name'],
                    $size['url'],
                    (int) ($size['width'] ?? 0),
                    (int) ($size['height'] ?? 0),
                );
            }
        }

        return new MediaItem(
            (string) $attachment['id'],
            (string) ($attachment['url'] ?? ''),
            MediaKind::fromMimeType($mimeType),
            $mimeType,
            (string) ($attachment['title'] ?? ''),
            (string) ($attachment['alt'] ?? ''),
            is_int($attachment['width'] ?? null) ? $attachment['width'] : null,
            is_int($attachment['height'] ?? null) ? $attachment['height'] : null,
            $sizes,
        );
    }
}
