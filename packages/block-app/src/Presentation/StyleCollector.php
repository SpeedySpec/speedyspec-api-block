<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Presentation;

use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * Collects style rules by class and writes a mobile-first stylesheet from them.
 *
 * @since 0.1.0
 */
class StyleCollector implements StyleCollectorInterface
{
    /**
     * Declarations by class, then by breakpoint name, with an empty name for every width.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private array $rules = [];

    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly BreakpointRegistryInterface $breakpoints,
        private readonly DesignTokenRegistryInterface $tokens,
    )
    {
    }

    public function add(StyleRule ...$rules): string
    {
        if ($rules === [])
        {
            return '';
        }

        $byBreakpoint = [];

        foreach ($rules as $rule)
        {
            $at = $rule->breakpoint ?? '';
            $byBreakpoint[$at] = [...($byBreakpoint[$at] ?? []), ...$rule->declarations];
        }

        // Sorted before hashing so the same styles written in another order still share a class.
        ksort($byBreakpoint);
        $class = 'b-' . substr(hash('sha256', (string) json_encode($byBreakpoint)), 0, 10);
        $this->rules[$class] = $byBreakpoint;

        return $class;
    }

    public function css(): string
    {
        $css = '';
        $tokens = [];

        foreach ($this->tokens->all() as $name => $value)
        {
            $tokens[DesignTokenRegistry::property($name)] = $value;
        }

        if ($tokens !== [])
        {
            $css .= ':root{' . $this->declarations($tokens) . '}';
        }

        $css .= $this->block('');

        foreach ($this->breakpoints->all() as $breakpoint)
        {
            $inner = $this->block($breakpoint->name);

            if ($inner !== '')
            {
                $css .= sprintf('@media (min-width:%dpx){%s}', $breakpoint->minWidth, $inner);
            }
        }

        return $css;
    }

    public function clear(): void
    {
        $this->rules = [];
    }

    /**
     * Writes every class's rule for one breakpoint, in the order the classes were first collected.
     *
     * @since 0.1.0
     */
    private function block(string $breakpoint): string
    {
        $css = '';

        foreach ($this->rules as $class => $byBreakpoint)
        {
            if (isset($byBreakpoint[$breakpoint]))
            {
                $css .= sprintf('.%s{%s}', $class, $this->declarations($byBreakpoint[$breakpoint]));
            }
        }

        return $css;
    }

    /**
     * @param array<string, string> $declarations
     *
     * @since 0.1.0
     */
    private function declarations(array $declarations): string
    {
        $parts = [];

        foreach ($declarations as $property => $value)
        {
            $parts[] = $property . ':' . $value;
        }

        return implode(';', $parts);
    }
}
