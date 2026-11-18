<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryRegistryInterface;

/**
 * Keeps the template libraries in memory, starting from the ones it is given.
 *
 * @since 0.1.0
 */
class BlockTemplateLibraryRegistry implements BlockTemplateLibraryRegistryInterface
{
    /**
     * @var array<string, BlockTemplateLibraryInterface>
     */
    private array $libraries = [];

    /**
     * @since 0.1.0
     */
    public function __construct(BlockTemplateLibraryInterface ...$libraries)
    {
        foreach ($libraries as $library)
        {
            $this->add($library);
        }
    }

    public function add(BlockTemplateLibraryInterface $library): void
    {
        $this->libraries[$library->name()] = $library;
    }

    public function remove(string $name): void
    {
        unset($this->libraries[$name]);
    }

    public function get(string $name): ?BlockTemplateLibraryInterface
    {
        return $this->libraries[$name] ?? null;
    }

    public function all(): array
    {
        return array_values($this->libraries);
    }
}
