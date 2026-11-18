<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Filesystem;

use ErrorException;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Domain\ValueObjects\CollectionName;
use Throwable;

/**
 * Keeps each record as a file under one directory per collection.
 *
 * A record's file is named by the hash of its key, never by the key, so no key can reach outside the directory,
 * collide with another on a case-insensitive disk, or exceed a file name limit. An index file holds the keys in
 * first-put order. Writes take an exclusive lock on the collection and replace files by renaming, so a reader never
 * sees a half-written record.
 *
 * @since 0.1.0
 */
class FilesystemRecordStore implements RecordStoreInterface
{
    private const string INDEX = 'index';

    private const string LOCK = '.lock';

    /**
     * @param string $directory
     *   Where collections are kept; created on the first write.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly string $directory)
    {
    }

    public function get(string $collection, string $key): ?string
    {
        CollectionName::assert($collection);
        $file = $this->recordFile($collection, $key);

        return $this->guarded(sprintf('read from "%s"', $collection), function () use ($file): ?string {
            if (!is_file($file))
            {
                return null;
            }

            $value = file_get_contents($file);

            return $value === false ? throw new ErrorException('The record file could not be read.') : $value;
        });
    }

    public function put(string $collection, string $key, string $value): void
    {
        CollectionName::assert($collection);

        $this->locked($collection, function () use ($collection, $key, $value): void {
            $this->replace($this->recordFile($collection, $key), $value);
            $keys = $this->index($collection);

            if (!in_array($key, $keys, true))
            {
                $this->writeIndex($collection, [...$keys, $key]);
            }
        });
    }

    public function delete(string $collection, string $key): void
    {
        CollectionName::assert($collection);

        if (!is_dir($this->collectionDirectory($collection)))
        {
            return;
        }

        $this->locked($collection, function () use ($collection, $key): void {
            $keys = $this->index($collection);

            if (in_array($key, $keys, true))
            {
                // The index goes first: a record missing from the list but still on disk is harmless, the reverse
                // would list a record that cannot be read.
                $this->writeIndex(
                    $collection,
                    array_values(array_filter($keys, static fn (string $stored): bool => $stored !== $key)),
                );
            }

            $file = $this->recordFile($collection, $key);

            if (is_file($file))
            {
                unlink($file);
            }
        });
    }

    public function keys(string $collection): array
    {
        CollectionName::assert($collection);

        return $this->guarded(sprintf('list "%s"', $collection), fn (): array => $this->index($collection));
    }

    /**
     * Runs a write while holding the collection's lock, creating the collection's directory if needed.
     *
     * @since 0.1.0
     */
    private function locked(string $collection, callable $write): void
    {
        $this->guarded(sprintf('write to "%s"', $collection), function () use ($collection, $write): void {
            $directory = $this->collectionDirectory($collection);

            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))
            {
                throw new ErrorException('The collection directory could not be created.');
            }

            $lock = fopen($directory . '/' . self::LOCK, 'c');

            if ($lock === false || !flock($lock, LOCK_EX))
            {
                throw new ErrorException('The collection could not be locked.');
            }

            try
            {
                $write();
            }
            finally
            {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        });
    }

    /**
     * Reads the collection's keys in first-put order.
     *
     * Keys are kept one per line in base64, since a key may hold any byte, a line break included.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function index(string $collection): array
    {
        $file = $this->collectionDirectory($collection) . '/' . self::INDEX;

        if (!is_file($file))
        {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false)
        {
            throw new ErrorException('The collection index could not be read.');
        }

        $keys = [];

        foreach ($lines as $line)
        {
            $key = base64_decode($line, true);

            if ($key === false)
            {
                throw new ErrorException('The collection index is damaged.');
            }

            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @param list<string> $keys
     *
     * @since 0.1.0
     */
    private function writeIndex(string $collection, array $keys): void
    {
        $lines = array_map(base64_encode(...), $keys);

        $this->replace(
            $this->collectionDirectory($collection) . '/' . self::INDEX,
            $lines === [] ? '' : implode("\n", $lines) . "\n",
        );
    }

    /**
     * Writes a file whole or not at all, by writing beside it and renaming over it.
     *
     * @since 0.1.0
     */
    private function replace(string $file, string $contents): void
    {
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $file))
        {
            if (is_file($temporary))
            {
                unlink($temporary);
            }

            throw new ErrorException('The file could not be written.');
        }
    }

    private function collectionDirectory(string $collection): string
    {
        return rtrim($this->directory, '/\\') . '/' . $collection;
    }

    private function recordFile(string $collection, string $key): string
    {
        return $this->collectionDirectory($collection) . '/' . hash('sha256', $key) . '.record';
    }

    /**
     * Runs a filesystem operation, turning any warning or failure into a storage exception.
     *
     * PHP reports most filesystem failures as warnings; left alone they would pass silently or surface as output.
     *
     * @throws StorageException
     *
     * @since 0.1.0
     */
    private function guarded(string $what, callable $operation): mixed
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try
        {
            return $operation();
        }
        catch (StorageException $e)
        {
            throw $e;
        }
        catch (Throwable $e)
        {
            throw new StorageException(sprintf('Could not %s: %s', $what, $e->getMessage()), 0, $e);
        }
        finally
        {
            restore_error_handler();
        }
    }
}
