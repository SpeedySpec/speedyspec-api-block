<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Storage;

use InvalidArgumentException;
use SpeedySpec\Block\App\Presentation\BreakpointRegistry;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;

/**
 * Keeps the site's breakpoints in a record store, so changes made from a builder's site styles screen last.
 *
 * It reads its defaults until the first change, then only the store, as {@link StoredDesignTokenRegistry} does: a
 * site that removes every breakpoint keeps none. Each record is the width in pixels, written as a decimal number.
 *
 * @since 0.1.0
 */
class StoredBreakpointRegistry implements BreakpointRegistryInterface
{
    /**
     * @var list<Breakpoint>
     */
    private readonly array $defaults;

    /**
     * @param list<Breakpoint>|null $defaults
     *   Read until the first change; null for the usual ones, {@link BreakpointRegistry::defaults()}.
     * @param string $collection
     *   Where the breakpoints are kept; one record per breakpoint, keyed by its name.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly RecordStoreInterface $store,
        ?array $defaults = null,
        private readonly string $collection = 'breakpoints',
    )
    {
        $defaults ??= BreakpointRegistry::defaults();

        foreach ($defaults as $breakpoint)
        {
            if (!$breakpoint instanceof Breakpoint)
            {
                throw new InvalidArgumentException(sprintf(
                    'Default breakpoints must be Breakpoint objects; one is %s.',
                    get_debug_type($breakpoint),
                ));
            }
        }

        $this->defaults = array_values($defaults);
    }

    public function add(Breakpoint $breakpoint): void
    {
        $this->takeOver();
        $this->store->put($this->collection, $breakpoint->name, (string) $breakpoint->minWidth);
    }

    public function remove(string $name): void
    {
        $this->takeOver();
        $this->store->delete($this->collection, $name);
    }

    public function get(string $name): ?Breakpoint
    {
        foreach ($this->all() as $breakpoint)
        {
            if ($breakpoint->name === $name)
            {
                return $breakpoint;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @throws StorageException
     *   When a stored record is not a usable breakpoint.
     */
    public function all(): array
    {
        return (new BreakpointRegistry(...($this->changed() ? $this->stored() : $this->defaults)))->all();
    }

    /**
     * @return list<Breakpoint>
     */
    private function stored(): array
    {
        $breakpoints = [];

        foreach ($this->store->keys($this->collection) as $name)
        {
            $width = $this->store->get($this->collection, $name);

            if ($width === null)
            {
                continue;
            }

            if (preg_match('/^[1-9][0-9]*\z/', $width) !== 1)
            {
                throw new StorageException(sprintf(
                    'The stored width of breakpoint "%s" is not a whole number of pixels.',
                    $name,
                ));
            }

            try
            {
                $breakpoints[] = new Breakpoint($name, (int) $width);
            }
            catch (InvalidStyleException $exception)
            {
                throw new StorageException(sprintf(
                    'The stored breakpoint "%s" cannot be used: %s',
                    $name,
                    $exception->getMessage(),
                ), 0, $exception);
            }
        }

        return $breakpoints;
    }

    private function changed(): bool
    {
        return $this->store->get(StoredDesignTokenRegistry::MARKERS, $this->collection) !== null;
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

        foreach ($this->defaults as $breakpoint)
        {
            $this->store->put($this->collection, $breakpoint->name, (string) $breakpoint->minWidth);
        }

        $this->store->put(StoredDesignTokenRegistry::MARKERS, $this->collection, '1');
    }
}
