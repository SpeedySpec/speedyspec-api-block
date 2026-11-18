<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Structure;

use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;
use SpeedySpec\Block\Domain\ValueObjects\ResponsiveValue;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * Turns the settings of the layout blocks into style rules.
 *
 * Every value written here is a whole number in a fixed range or one of a fixed list of words, so nothing a
 * person typed reaches a stylesheet.
 *
 * @since 0.1.0
 */
class Layout
{
    public const array ALIGNMENTS = ['stretch', 'start', 'center', 'end'];

    /**
     * Columns: stacked below a breakpoint and in a row from it upward, or always in a row.
     *
     * @param array<string, mixed> $values
     *
     * @return list<StyleRule>
     *
     * @since 0.1.0
     */
    public static function columns(array $values): array
    {
        $align = in_array($values['align'] ?? null, self::ALIGNMENTS, true) ? $values['align'] : 'stretch';
        $align = in_array($align, ['start', 'end'], true) ? 'flex-' . $align : $align;
        $from = is_string($values['stackBelow'] ?? null) ? $values['stackBelow'] : 'tablet';
        $row = ['flex-direction' => 'row', 'align-items' => $align];

        if (!Breakpoint::isName($from))
        {
            return [new StyleRule(['display' => 'flex', ...$row])];
        }

        return [new StyleRule(['display' => 'flex', 'flex-direction' => 'column']), new StyleRule($row, $from)];
    }

    /**
     * A column: its share of the row, which may differ by breakpoint.
     *
     * @param array<string, mixed> $values
     *
     * @return list<StyleRule>
     *
     * @since 0.1.0
     */
    public static function column(array $values): array
    {
        return self::counted(
            $values['span'] ?? 1,
            static fn (int $span): array => ['flex' => $span . ' 1 0%'],
            // Without this a column holding something wide grows past its share instead of letting it shrink.
            ['min-width' => '0'],
        );
    }

    /**
     * A grid: its number of equal columns, which may differ by breakpoint.
     *
     * @param array<string, mixed> $values
     *
     * @return list<StyleRule>
     *
     * @since 0.1.0
     */
    public static function grid(array $values): array
    {
        return self::counted(
            $values['columns'] ?? 1,
            static fn (int $count): array => ['grid-template-columns' => sprintf('repeat(%d, minmax(0, 1fr))', $count)],
            ['display' => 'grid'],
            true,
        );
    }

    /**
     * Builds one rule per width for a responsive count from 1 to 12.
     *
     * @param callable(int): array<string, string> $declare
     *   Writes the declarations for one count.
     * @param array<string, string> $always
     *   Declarations for every width, whatever the count.
     * @param bool $first
     *   Whether the fixed declarations come before the counted ones in the base rule.
     *
     * @return list<StyleRule>
     *
     * @since 0.1.0
     */
    private static function counted(mixed $stored, callable $declare, array $always, bool $first = false): array
    {
        $value = ResponsiveValue::from($stored);
        $base = $declare(self::count($value->base()));
        $rules = [new StyleRule($first ? [...$always, ...$base] : [...$base, ...$always])];

        foreach ($value->overrides() as $breakpoint => $count)
        {
            $rules[] = new StyleRule($declare(self::count($count)), $breakpoint);
        }

        return $rules;
    }

    private static function count(mixed $stored): int
    {
        return is_int($stored) ? max(1, min(12, $stored)) : 1;
    }
}
