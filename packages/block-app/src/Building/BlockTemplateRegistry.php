<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Building;

use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Keeps code-defined templates in memory, by name.
 *
 * @since 0.1.0
 */
class BlockTemplateRegistry implements BlockTemplateRegistryInterface
{
    /**
     * @var array<string, BlockTemplate>
     */
    private array $templates = [];

    public function add(BlockTemplate $template): void
    {
        $this->templates[$template->name] = $template;
    }

    public function remove(string $name): void
    {
        unset($this->templates[$name]);
    }

    public function find(string $name): ?BlockTemplate
    {
        return $this->templates[$name] ?? null;
    }

    public function all(): array
    {
        return array_values($this->templates);
    }
}
