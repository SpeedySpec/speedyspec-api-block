<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Filesystem;

use ErrorException;
use InvalidArgumentException;
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
 * The two files of a write are ordered so that an interruption leaves at worst a key that is listed but has no
 * record, which reads as missing; never a record that can be read but is not listed, or a deleted record that
 * comes back.
 *
 * @since 0.1.0
 */
class FilesystemRecordStore implements RecordStoreInterface
{
    private const string INDEX = 'index';

    private const string LOCK = '.lock';

    private readonly string $directory;

    /**
     * @param string $directory
     *   Where collections are kept; created on the first write.
     *
     * @throws InvalidArgumentException
     *   When the directory is empty or the root of the disk, which is what an unset setting would turn into.
     *
     * @since 0.1.0
     */
    public function __construct(string $directory)
    {
        $this->directory = rtrim(trim($directory), '/\\');

        if ($this->directory === '')
        {
            throw new InvalidArgumentException('A record store needs a directory of its own.');
        }
    }

    public function get(string $collection, string $key): ?string
    {
        CollectionName::assert($collection);
        $file = $this->recordFile($collection, $key);

        return $this->guarded(sprintf('read from "%s"', $collection), function () use ($collection, $file): ?string {
            $this->assertReachable($collection);

            try
            {
                $value = file_get_contents($file);
            }
            catch (ErrorException $e)
            {
                // Not found, or deleted between looking and reading: both are simply "no record".
                if (!file_exists($file))
                {
                    return null;
                }

                throw $e;
            }

            return $value === false ? throw new ErrorException('The record file could not be read.') : $value;
        });
    }

    public function put(string $collection, string $key, string $value): void
    {
        CollectionName::assert($collection);

        $this->locked($collection, function () use ($collection, $key, $value): void {
            // Read first, so a damaged index stops the write before anything changes.
            $keys = $this->index($collection);

            if (!in_array($key, $keys, true))
            {
                $this->writeIndex($collection, [...$keys, $key]);
            }

            $this->replace($this->recordFile($collection, $key), $value);
        });
    }

    public function delete(string $collection, string $key): void
    {
        CollectionName::assert($collection);

        $exists = $this->guarded(sprintf('write to "%s"', $collection), function () use ($collection): bool {
            $this->assertReachable($collection);

            return is_dir($this->collectionDirectory($collection));
        });

        if (!$exists)
        {
            return;
        }

        $this->locked($collection, function () use ($collection, $key): void {
            $file = $this->recordFile($collection, $key);

            if (is_file($file))
            {
                unlink($file);
            }

            $keys = $this->index($collection);

            if (in_array($key, $keys, true))
            {
                $this->writeIndex(
                    $collection,
                    array_values(array_filter($keys, static fn (string $stored): bool => $stored !== $key)),
                );
            }
        });
    }

    public function keys(string $collection): array
    {
        CollectionName::assert($collection);

        return $this->guarded(sprintf('list "%s"', $collection), function () use ($collection): array {
            $this->assertReachable($collection);

            return $this->index($collection);
        });
    }

    /**
     * Creates a directory and its parents.
     *
     * Kept apart so a test can stand in for another writer creating the same directory at the same moment.
     *
     * @since 0.1.0
     */
    protected function makeDirectory(string $directory): bool
    {
        return mkdir($directory, 0775, true);
    }

    /**
     * Writes a file whole or not at all, by writing beside it and renaming over it.
     *
     * @since 0.1.0
     */
    protected function replace(string $file, string $contents): void
    {
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        try
        {
            if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $file))
            {
                throw new ErrorException('The file could not be written.');
            }
        }
        finally
        {
            if (is_file($temporary))
            {
                unlink($temporary);
            }
        }
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

            if (!is_dir($directory))
            {
                try
                {
                    $made = $this->makeDirectory($directory);
                }
                catch (ErrorException)
                {
                    $made = false;
                }

                // Another writer creating it at the same moment is success, not failure.
                if (!$made && !is_dir($directory))
                {
                    throw new ErrorException('The collection directory could not be created.');
                }
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
     * Makes sure a missing file means "no record" rather than "cannot look".
     *
     * PHP answers "not a file" both for a path that does not exist and for one it may not look at, so the nearest
     * directory that does exist is checked: it must be a directory, and one this process may read and enter.
     *
     * @throws ErrorException
     *   When storage exists but cannot be read.
     *
     * @since 0.1.0
     */
    private function assertReachable(string $collection): void
    {
        $path = $this->collectionDirectory($collection);

        while (!file_exists($path) && dirname($path) !== $path)
        {
            $path = dirname($path);
        }

        if (!is_dir($path) || !is_readable($path) || !is_executable($path))
        {
            throw new ErrorException('The storage directory exists but cannot be read.');
        }
    }

    /**
     * Reads the collection's keys in first-put order.
     *
     * Keys are kept one per line as a colon and the key in base64, since a key may hold any byte, a line break
     * included. Blank and repeated lines are passed over; any other line means the index is damaged.
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
            if ($line === '')
            {
                continue;
            }

            // Every line starts with a colon, so the empty key still makes a line that is not blank.
            $key = str_starts_with($line, ':') ? base64_decode(substr($line, 1), true) : false;

            if ($key === false)
            {
                throw new ErrorException('The collection index is damaged.');
            }

            if (!in_array($key, $keys, true))
            {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Replaces the collection's index with the given keys.
     *
     * @param list<string> $keys
     *
     * @since 0.1.0
     */
    private function writeIndex(string $collection, array $keys): void
    {
        $lines = array_map(static fn (string $key): string => ':' . base64_encode($key), $keys);

        $this->replace(
            $this->collectionDirectory($collection) . '/' . self::INDEX,
            $lines === [] ? '' : implode("\n", $lines) . "\n",
        );
    }

    private function collectionDirectory(string $collection): string
    {
        return $this->directory . '/' . $collection;
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
