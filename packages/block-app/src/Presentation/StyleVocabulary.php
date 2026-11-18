<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Presentation;

use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\ResponsiveValue;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * The shared words for how a block looks: spacing, size, text and colour, each of which may vary by breakpoint.
 *
 * Block packages and builders share this vocabulary instead of each inventing its own names and value formats.
 * It is deliberately short and every value has a strict format, so stored content can never carry arbitrary CSS.
 *
 * @since 0.1.0
 */
class StyleVocabulary
{
    public const string NAMESPACE = 'core/style';

    private const string TOKEN = 'token:' . DesignTokenRegistry::NAME_PATTERN;

    private const string LENGTH = '(0|auto|-?[0-9]+(\.[0-9]+)?(px|rem|em|%|vw|vh|ch)|' . self::TOKEN . ')';

    /**
     * Setting name to the property it writes, the format its values take, and its label.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const array WORDS = [
        'padding' => ['padding', self::LENGTH . '( ' . self::LENGTH . '){0,3}', 'Padding'],
        'margin' => ['margin', self::LENGTH . '( ' . self::LENGTH . '){0,3}', 'Margin'],
        'gap' => ['gap', self::LENGTH . '( ' . self::LENGTH . ')?', 'Gap'],
        'width' => ['width', self::LENGTH, 'Width'],
        'maxWidth' => ['max-width', self::LENGTH, 'Maximum width'],
        'minHeight' => ['min-height', self::LENGTH, 'Minimum height'],
        'textAlign' => ['text-align', '(left|center|right|justify|start|end)', 'Text alignment'],
        'fontSize' => ['font-size', self::LENGTH, 'Text size'],
        'fontWeight' => ['font-weight', '(normal|bold|[1-9]00)', 'Text weight'],
        'lineHeight' => ['line-height', '([0-9]+(\.[0-9]+)?|' . self::LENGTH . ')', 'Line height'],
        'color' => ['color', '(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,30}|' . self::TOKEN . ')', 'Text colour'],
        'background' => ['background-color', '(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,30}|' . self::TOKEN . ')', 'Background'],
        'borderRadius' => ['border-radius', self::LENGTH . '( ' . self::LENGTH . '){0,3}', 'Corner radius'],
    ];

    /**
     * Builds the decorator that lets every block type carry these settings.
     *
     * Each setting is a responsive value: one string, or a map of `base` and breakpoint names to strings.
     *
     * @since 0.1.0
     */
    public static function decorator(): Decorator
    {
        $properties = [];

        foreach (self::WORDS as $name => [, $format, $title])
        {
            $pattern = '^' . $format . '$';
            $properties[$name] = [
                'type' => ['string', 'object'],
                'title' => $title,
                'pattern' => $pattern,
                'additionalProperties' => ['type' => 'string', 'pattern' => $pattern],
                'x-control' => 'responsive',
            ];
        }

        return new Decorator(self::NAMESPACE, [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ]);
    }

    /**
     * Removes whatever in stored style settings is not in the vocabulary or does not fit its format.
     *
     * Run before a block is checked, so a cosmetic value that was stored without going through the editor costs
     * the block that one style, not its place on the page.
     *
     * @param array<array-key, mixed> $settings
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public static function clean(array $settings): array
    {
        $clean = [];

        foreach (self::WORDS as $name => [, $format])
        {
            $stored = $settings[$name] ?? null;
            $fits = static fn (mixed $value): bool => is_string($value)
                && preg_match('/^' . $format . '\z/', $value) === 1;

            if ($fits($stored))
            {
                $clean[$name] = $stored;
            }
            elseif (is_array($stored) && !array_is_list($stored))
            {
                $kept = array_filter($stored, $fits);

                if ($kept !== [])
                {
                    $clean[$name] = $kept;
                }
            }
        }

        return $clean;
    }

    /**
     * Turns stored style settings into rules: one for every width, and one per breakpoint that has overrides.
     *
     * Declarations follow the vocabulary's order, not the stored order, so blocks styled alike produce equal
     * rules. A value that does not fit its format is dropped; it can only be there if it was stored without
     * going through the editor, and it must not reach a stylesheet.
     *
     * @param array<array-key, mixed> $settings
     *
     * @return list<StyleRule>
     *
     * @since 0.1.0
     */
    public static function rules(array $settings): array
    {
        $byBreakpoint = [];

        foreach (self::WORDS as $name => [$property, $format])
        {
            $value = ResponsiveValue::from($settings[$name] ?? null);

            foreach (['' => $value->base(), ...$value->overrides()] as $at => $stored)
            {
                if (is_string($stored) && preg_match('/^' . $format . '\z/', $stored) === 1)
                {
                    $byBreakpoint[$at][$property] = self::css($stored);
                }
            }
        }

        $rules = [];

        foreach ($byBreakpoint as $at => $declarations)
        {
            try
            {
                $rules[] = new StyleRule($declarations, $at === '' ? null : (string) $at);
            }
            catch (InvalidStyleException)
            {
                // A format above let through something the rule itself refuses; the rule's check wins.
            }
        }

        return $rules;
    }

    /**
     * Writes a stored value as CSS, turning each `token:name` into a reference to its custom property.
     *
     * @since 0.1.0
     */
    private static function css(string $value): string
    {
        return (string) preg_replace_callback(
            '/' . self::TOKEN . '/',
            static fn (array $found): string => 'var(' . DesignTokenRegistry::property(substr($found[0], 6)) . ')',
            $value,
        );
    }
}
