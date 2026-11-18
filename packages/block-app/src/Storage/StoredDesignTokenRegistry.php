<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Storage;

use SpeedySpec\Block\App\Presentation\DesignTokenRegistry;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;

/**
 * Keeps the site's design tokens in a record store, so changes made from a builder's site styles screen last.
 *
 * Until the first change the registry reads the defaults it is given, so a host can ship tokens in code and a site
 * that never changes them never writes anything. The first change writes the defaults and a marker, and from then
 * on only the store is read: a token removed stays removed, even when it is still among the defaults.
 *
 * @since 0.1.0
 */
class StoredDesignTokenRegistry implements DesignTokenRegistryInterface
{
    /**
     * The collection that holds one "has been changed" marker per stored registry, keyed by its own collection.
     *
     * @since 0.1.0
     */
    public const string MARKERS = 'style-registries';

    /**
     * @param array<string, string> $defaults
     *   Read until the first change; checked here, so a bad default is found when the site starts.
     * @param string $collection
     *   Where the tokens are kept; one record per token, keyed by its name.
     *
     * @throws \SpeedySpec\Block\Domain\Exceptions\InvalidStyleException
     *   When a default is not a usable token.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly RecordStoreInterface $store,
        private readonly array $defaults = [],
        private readonly string $collection = 'design-tokens',
    )
    {
        foreach ($defaults as $name => $value)
        {
            DesignTokenRegistry::assertToken((string) $name, $value);
        }
    }

    public function set(string $name, string $value): void
    {
        DesignTokenRegistry::assertToken($name, $value);
        $this->takeOver();
        $this->store->put($this->collection, $name, $value);
    }

    public function remove(string $name): void
    {
        $this->takeOver();
        $this->store->delete($this->collection, $name);
    }

    public function get(string $name): ?string
    {
        return $this->all()[$name] ?? null;
    }

    public function all(): array
    {
        $tokens = [];

        if (!$this->changed())
        {
            $tokens = $this->defaults;
        }
        else
        {
            foreach ($this->store->keys($this->collection) as $name)
            {
                $value = $this->store->get($this->collection, $name);

                if ($value !== null)
                {
                    $tokens[$name] = $value;
                }
            }
        }

        ksort($tokens, SORT_STRING);

        return $tokens;
    }

    private function changed(): bool
    {
        return $this->store->get(self::MARKERS, $this->collection) !== null;
    }

    /**
     * Writes the defaults before the first change, so the change is made to what the site was showing.
     */
    private function takeOver(): void
    {
        if ($this->changed())
        {
            return;
        }

        foreach ($this->defaults as $name => $value)
        {
            $this->store->put($this->collection, (string) $name, $value);
        }

        $this->store->put(self::MARKERS, $this->collection, '1');
    }
}
