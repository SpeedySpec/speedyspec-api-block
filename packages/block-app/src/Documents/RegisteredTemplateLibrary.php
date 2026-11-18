<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Documents;

use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Offers the templates block packages and the host define in code as a library, so a builder lists them like any other.
 *
 * @since 0.1.0
 */
class RegisteredTemplateLibrary implements BlockTemplateLibraryInterface
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly BlockTemplateRegistryInterface $templates)
    {
    }

    public function name(): string
    {
        return 'core/registered';
    }

    public function title(): string
    {
        return 'Built in';
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
