<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Connects a setting to a data source, so its value is fetched at resolve time instead of stored.
 *
 * @since 0.1.0
 */
class Binding
{
    /**
     * @param array<string, mixed> $arguments
     *   Arguments passed to the source.
     */
    private function __construct(
        public readonly string $source,
        public readonly array $arguments,
        public readonly mixed $fallback,
        public readonly bool $hasFallback,
    ) {
    }

    /**
     * Creates a binding to a source, with no fallback.
     *
     * @param string $source
     *   The data source identifier.
     * @param array<string, mixed> $arguments
     *   Arguments passed to the source.
     *
     * @since 0.1.0
     */
    public static function to(string $source, array $arguments = []): self
    {
        return new self($source, $arguments, null, false);
    }

    /**
     * Adds a value to use when the source yields nothing; null is a valid fallback.
     *
     * @param mixed $fallback
     *   The fallback value.
     *
     * @since 0.1.0
     */
    public function withFallback(mixed $fallback): self
    {
        return new self($this->source, $this->arguments, $fallback, true);
    }

    /**
     * Serialises the binding; the `fallback` key is present only when one was set.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        $data = ['source' => $this->source, 'arguments' => $this->arguments];

        if ($this->hasFallback)
        {
            $data['fallback'] = $this->fallback;
        }

        return $data;
    }

    /**
     * Builds a binding from its serialised form.
     *
     * @param array<string, mixed> $data
     *   The serialised binding.
     *
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        $binding = self::to((string) $data['source'], (array) ($data['arguments'] ?? []));

        return array_key_exists('fallback', $data) ? $binding->withFallback($data['fallback']) : $binding;
    }
}
