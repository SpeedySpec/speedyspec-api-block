<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Filesystem;

use RuntimeException;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaPage;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;

/**
 * Keeps uploaded media as files in one directory, with an index beside them.
 *
 * Every file is stored under a name the library makes, so what a person called a file can never choose where it is
 * written; their name is kept only as the item's title. No smaller sizes are made: resizing images is the host's.
 *
 * @since 0.1.0
 */
class FilesystemMediaLibrary implements MediaLibraryInterface
{
    private const string INDEX = 'index.json';

    private const int TITLE_LENGTH = 120;

    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'application/pdf' => 'pdf',
    ];

    /**
     * @param string $directory
     *   Where the files and the index are kept; made when the first file is uploaded.
     * @param string $baseUrl
     *   Where the host serves that directory, ending in a slash.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $baseUrl,
    )
    {
    }

    public function find(string $id): ?MediaItem
    {
        $record = $this->records()[$id] ?? null;

        return $record === null ? null : $this->item($record);
    }

    public function search(MediaQuery $query): MediaPage
    {
        $matches = [];

        foreach (array_reverse($this->records()) as $record)
        {
            $item = $this->item($record);

            if (($query->kind === null || $item->kind === $query->kind)
                && ($query->search === '' || mb_stripos($item->title, $query->search) !== false))
            {
                $matches[] = $item;
            }
        }

        return new MediaPage(
            array_slice($matches, ($query->page - 1) * $query->perPage, $query->perPage),
            count($matches),
        );
    }

    public function upload(MediaUpload $upload): MediaItem
    {
        $this->ensureDirectory();
        $id = bin2hex(random_bytes(8));
        $file = $id . '.' . (self::EXTENSIONS[$upload->mimeType] ?? 'bin');

        if (file_put_contents($this->directory . '/' . $file, $upload->bytes) === false)
        {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        // A file whose first bytes say image but which is not one has no size; that is not an error.
        $size = $upload->kind() === MediaKind::Image ? $this->imageSize($this->directory . '/' . $file) : false;
        $record = [
            'id' => $id,
            'file' => $file,
            'mimeType' => $upload->mimeType,
            'title' => self::title($upload->name),
            'width' => $size === false ? null : $size[0],
            'height' => $size === false ? null : $size[1],
        ];

        $this->withIndex(function (array $records) use ($record): array
        {
            $records[$record['id']] = $record;

            return $records;
        });

        return $this->item($record);
    }

    /**
     * Gives the stored file of an item, for a host to serve.
     *
     * @return string|null
     *   The file's path, or null when the library has no such item.
     *
     * @since 0.1.0
     */
    public function path(string $id): ?string
    {
        $record = $this->records()[$id] ?? null;

        if ($record === null)
        {
            return null;
        }

        $path = realpath($this->directory . '/' . $record['file']);

        return $path === false ? null : $path;
    }

    /**
     * Turns the name a file was uploaded with into a title that is safe to show and to search.
     *
     * @since 0.1.0
     */
    public static function title(string $name): string
    {
        $parts = preg_split('#[/\\\\]#', $name) ?: [''];
        $base = (string) end($parts);
        $base = (string) preg_replace('/[\p{C}]+/u', '', $base);
        $base = (string) preg_replace('/[^\p{L}\p{N} ._-]+/u', '', $base);
        $base = trim((string) preg_replace('/\s+/u', ' ', $base));
        $base = rtrim(mb_substr($base, 0, self::TITLE_LENGTH));

        return preg_match('/[\p{L}\p{N}]/u', $base) === 1 ? $base : 'upload';
    }

    /**
     * @param array{id: string, file: string, mimeType: string, title: string, width: ?int, height: ?int} $record
     */
    private function item(array $record): MediaItem
    {
        return new MediaItem(
            $record['id'],
            $this->baseUrl . $record['file'],
            MediaKind::fromMimeType($record['mimeType']),
            $record['mimeType'],
            $record['title'],
            '',
            $record['width'],
            $record['height'],
        );
    }

    /**
     * Reads the index, oldest first, keyed by id.
     *
     * @return array<string, array<string, mixed>>
     *   Each record has `id`, `file`, `mimeType`, `title`, `width` and `height`.
     */
    private function records(): array
    {
        $index = $this->directory . '/' . self::INDEX;
        $raw = is_file($index) ? file_get_contents($index) : false;
        $records = $raw === false ? [] : json_decode($raw, true);

        return is_array($records) ? $records : [];
    }

    /**
     * Changes the index while holding a lock, so two uploads at once both stay listed, and replaces it in one step.
     *
     * @param callable(array<string, array<string, mixed>>): array<string, array<string, mixed>> $change
     */
    private function withIndex(callable $change): void
    {
        $lock = fopen($this->directory . '/.lock', 'c');

        if ($lock === false || !flock($lock, LOCK_EX))
        {
            throw new RuntimeException('The media index could not be locked.');
        }

        try
        {
            $temporary = $this->directory . '/' . self::INDEX . '.' . bin2hex(random_bytes(4));
            $json = json_encode($change($this->records()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $written = file_put_contents($temporary, $json) !== false;

            if (!$written || !rename($temporary, $this->directory . '/' . self::INDEX))
            {
                throw new RuntimeException('The media index could not be written.');
            }
        }
        finally
        {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array{0: int, 1: int}|false
     */
    private function imageSize(string $path): array|false
    {
        set_error_handler(static fn (): bool => true);

        try
        {
            $size = getimagesize($path);
        }
        finally
        {
            restore_error_handler();
        }

        return $size === false ? false : [$size[0], $size[1]];
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory))
        {
            throw new RuntimeException('The media directory could not be made.');
        }
    }
}
