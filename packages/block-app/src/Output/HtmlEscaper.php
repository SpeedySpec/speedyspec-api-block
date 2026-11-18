<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Output;

use SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface;
use SpeedySpec\Block\Domain\Contracts\RichTextSanitizerInterface;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;

/**
 * Escapes values for HTML.
 *
 * Existing entities are encoded again on purpose: a stored setting is data, so `&amp;` typed by an author must
 * appear as those five characters, not as an ampersand.
 *
 * @since 0.1.0
 */
class HtmlEscaper implements OutputEscaperInterface
{
    private const FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    private const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Attributes a browser treats as a link, whose value must pass the same check as any other url.
     */
    private const URL_ATTRIBUTES = [
        'href', 'src', 'action', 'formaction', 'xlink:href', 'poster', 'data', 'ping', 'background', 'cite',
        'manifest',
    ];

    public function target(): string
    {
        return 'html';
    }

    /**
     * @param RichTextSanitizerInterface|null $richText
     *   Cleans formatted text; the default sanitizer when none is given.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly ?RichTextSanitizerInterface $richText = null)
    {
    }

    public function richText(string $value): string
    {
        return ($this->richText ?? new RichTextSanitizer())->clean($value);
    }

    public function text(string $value): string
    {
        return htmlspecialchars($value, self::FLAGS, 'UTF-8');
    }

    public function attribute(string $value): string
    {
        return htmlspecialchars($value, self::FLAGS, 'UTF-8');
    }

    public function url(string $value): string
    {
        $value = trim($value);

        // Browsers ignore control characters and whitespace inside a scheme, so the check must ignore them too.
        $compact = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $value);

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $compact, $match) === 1)
        {
            if (!in_array(strtolower($match[1]), self::SCHEMES, true))
            {
                return '';
            }
        }
        elseif (preg_match('/^[^\/?#]*:/', $compact) === 1)
        {
            // A colon before any path, query or fragment that is not a well-formed scheme is not a relative link.
            return '';
        }

        if ($compact !== $value && preg_match('/[\x00-\x1F\x7F]/', $value) === 1)
        {
            return '';
        }

        return $this->attribute($value);
    }

    public function attributes(AttributeBag $bag): string
    {
        $parts = [];

        if ($bag->id() !== null)
        {
            $parts[] = sprintf('id="%s"', $this->attribute($bag->id()));
        }

        if ($bag->classes() !== [])
        {
            $parts[] = sprintf('class="%s"', $this->attribute(implode(' ', $bag->classes())));
        }

        if ($bag->styles() !== [])
        {
            $declarations = [];

            foreach ($bag->styles() as $property => $value)
            {
                $declarations[] = $property . ': ' . $value;
            }

            $parts[] = sprintf('style="%s"', $this->attribute(implode('; ', $declarations)));
        }

        foreach ($bag->attributes() as $name => $value)
        {
            if (!in_array(strtolower($name), self::URL_ATTRIBUTES, true))
            {
                $parts[] = sprintf('%s="%s"', $name, $this->attribute($value));

                continue;
            }

            $url = $this->url($value);

            // A link the url check refused is left out entirely; an attribute bag may hold stored content.
            if ($url !== '' || trim($value) === '')
            {
                $parts[] = sprintf('%s="%s"', $name, $url);
            }
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }
}
