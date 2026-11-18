<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Output;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use SpeedySpec\Block\Domain\Contracts\RichTextSanitizerInterface;

/**
 * Cleans formatted text by parsing it as HTML and writing back only what is allowed.
 *
 * Parsing rather than matching patterns means misnested and unclosed tags, and entities, are read the way a browser
 * reads them, so nothing can be smuggled past by writing it oddly. Everything written is escaped afresh.
 *
 * @since 0.1.0
 */
class RichTextSanitizer implements RichTextSanitizerInterface
{
    private const array ELEMENTS = ['strong', 'em', 's', 'code', 'sub', 'sup', 'mark'];

    private const array SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Elements nested deeper than this are written as their text, so a hostile value cannot make the output huge.
     */
    private const int MAX_DEPTH = 32;

    /**
     * A little under the nesting libxml's HTML parser keeps before it starts dropping content.
     */
    private const int PARSER_DEPTH = 200;

    public function clean(string $html): string
    {
        if ($html === '')
        {
            return '';
        }

        $html = str_replace(["\r\n", "\r"], "\n", $this->valid($html));

        // The parser gives up on nesting past its own limit and drops what is inside. Losing a person's words is
        // worse than losing their formatting, so a value nested that deeply is kept as plain text instead.
        if ($this->nesting($html) > self::PARSER_DEPTH)
        {
            return $this->text(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $document = new DOMDocument();
        $errors = libxml_use_internal_errors(true);

        try
        {
            $document->loadHTML(
                '<?xml encoding="UTF-8"><body>' . $html . '</body>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_PARSEHUGE,
            );
        }
        finally
        {
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null)
        {
            return $this->text(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $this->children($body, 0);
    }

    /**
     * Replaces invalid UTF-8 with the replacement character, and nothing else, before the value is parsed.
     *
     * @since 0.1.0
     */
    private function valid(string $html): string
    {
        return htmlspecialchars_decode(htmlspecialchars($html, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_NOQUOTES);
    }

    /**
     * Measures how deeply the tags in a value open inside one another, as a parser would nest them.
     *
     * Tags that are never closed count as open, as the parser treats them. Only `br` is known to hold nothing.
     *
     * @since 0.1.0
     */
    private function nesting(string $html): int
    {
        preg_match_all('#<(/?)([a-zA-Z][a-zA-Z0-9]*)[^>]*?(/?)>#', $html, $tags, PREG_SET_ORDER);
        $depth = 0;
        $deepest = 0;

        foreach ($tags as [, $closing, $name, $selfClosing])
        {
            if (strtolower($name) === 'br' || $selfClosing === '/')
            {
                continue;
            }

            $depth = $closing === '/' ? max(0, $depth - 1) : $depth + 1;
            $deepest = max($deepest, $depth);
        }

        return $deepest;
    }

    /**
     * Writes the cleaned form of every child of a node, in order.
     *
     * @since 0.1.0
     */
    private function children(DOMNode $node, int $depth): string
    {
        $out = '';

        foreach ($node->childNodes as $child)
        {
            $out .= $this->node($child, $depth);
        }

        return $out;
    }

    /**
     * Writes one node: its text, an allowed element with its children, or, for anything else, only its children.
     *
     * @since 0.1.0
     */
    private function node(DOMNode $node, int $depth): string
    {
        if ($node instanceof DOMText)
        {
            return $this->text($node->data);
        }

        if ($node instanceof DOMComment || !$node instanceof DOMElement)
        {
            return '';
        }

        $name = strtolower($node->tagName);

        if ($name === 'br')
        {
            return '<br>';
        }

        if ($depth >= self::MAX_DEPTH)
        {
            return $this->text($node->textContent);
        }

        $inner = $this->children($node, $depth + 1);

        if (in_array($name, self::ELEMENTS, true))
        {
            return sprintf('<%1$s>%2$s</%1$s>', $name, $inner);
        }

        if ($name === 'a')
        {
            $href = $this->href($node->getAttribute('href'));

            if ($href === null)
            {
                return $inner;
            }

            // Quotes are escaped here, unlike in text: this value sits inside an attribute.
            $href = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            return sprintf('<a href="%s">%s</a>', $href, $inner);
        }

        // Anything else, a script included, leaves only its text, which is escaped and so cannot run.
        return $inner;
    }

    /**
     * Keeps a link only to the web, mail, a phone, or a path on the same site.
     *
     * @return string|null
     *   Null when the link must go.
     */
    private function href(string $href): ?string
    {
        $href = trim($href);
        $compact = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $href);

        // Browsers read `/\` as `//`, the start of another site's address.
        if ($href === '' || str_starts_with($compact, '//') || str_starts_with($compact, '/\\'))
        {
            return null;
        }

        if (str_starts_with($compact, '/') || str_starts_with($compact, '#'))
        {
            return $href;
        }

        $colon = strpos($compact, ':');

        return $colon !== false && in_array(strtolower(substr($compact, 0, $colon)), self::SCHEMES, true)
            ? $href
            : null;
    }

    /**
     * Escapes text to be placed between elements.
     *
     * @since 0.1.0
     */
    private function text(string $value): string
    {
        // Quotes are escaped too, though the result is element content: an output may still place formatted text in
        // an attribute, such as a title, and must not be able to break out of it.
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
