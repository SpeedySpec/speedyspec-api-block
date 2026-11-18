<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Documents;

use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Offers the templates people have saved as a library, so a builder lists them like any other.
 *
 * @since 0.1.0
 */
class SavedTemplateLibrary implements BlockTemplateLibraryInterface
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly BlockTemplateRepositoryInterface $templates)
    {
    }

    public function name(): string
    {
        return 'core/saved';
    }

    public function title(): string
    {
        return 'Saved';
    }

    public function all(): array
    {
        return $this->templates->all();
    }

    public function find(string $name): ?BlockTemplate
    {
        return $this->templates->find($name);
    }
}
