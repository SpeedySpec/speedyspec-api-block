<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Keeps saved templates in memory, for tests and for hosts with no storage.
 *
 * Templates are immutable, so handing back the stored instance is safe.
 *
 * @since 0.1.0
 */
class InMemoryBlockTemplateRepository implements BlockTemplateRepositoryInterface
{
    /**
     * @var array<string, BlockTemplate>
     */
    private array $templates = [];

    public function save(BlockTemplate $template): void
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
