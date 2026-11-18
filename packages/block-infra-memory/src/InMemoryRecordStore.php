<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\ValueObjects\CollectionName;

/**
 * Keeps records in memory, for tests and as the reference a real record store is compared against.
 *
 * @since 0.1.0
 */
class InMemoryRecordStore implements RecordStoreInterface
{
    /**
     * Records as key and value pairs, in first-put order. Pairs are used instead of array keys because PHP turns
     * numeric string keys into integers, which would change them.
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private array $records = [];

    public function get(string $collection, string $key): ?string
    {
        CollectionName::assert($collection);

        foreach ($this->records[$collection] ?? [] as [$stored, $value])
        {
            if ($stored === $key)
            {
                return $value;
            }
        }

        return null;
    }

    public function put(string $collection, string $key, string $value): void
    {
        CollectionName::assert($collection);

        foreach ($this->records[$collection] ?? [] as $index => [$stored])
        {
            if ($stored === $key)
            {
                $this->records[$collection][$index][1] = $value;

                return;
            }
        }

        $this->records[$collection][] = [$key, $value];
    }

    public function delete(string $collection, string $key): void
    {
        CollectionName::assert($collection);

        $this->records[$collection] = array_values(array_filter(
            $this->records[$collection] ?? [],
            static fn (array $record): bool => $record[0] !== $key,
        ));
    }

    public function keys(string $collection): array
    {
        CollectionName::assert($collection);

        return array_map(static fn (array $record): string => $record[0], $this->records[$collection] ?? []);
    }
}
