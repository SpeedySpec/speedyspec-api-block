<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Text;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\ResponsiveValue;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;
use SpeedySpec\Block\Text\Domain\TextTypesPackage;

/**
 * Draws the text blocks as plain, accessible markup.
 *
 * Formatted text goes through the escaper's `richText()`, plain text through `text()`, and every value written into
 * an attribute through the attribute bag, so nothing stored reaches the page unchecked. The attribute bag, with the
 * shared style settings, goes on each block's outermost element.
 *
 * @since 0.1.0
 */
class TextOutput implements BlockOutputInterface
{
    /**
     * @param string $block
     *   Which text block this output draws, by its name after `core/`.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly string $block,
        private readonly StyleCollectorInterface $styles,
    )
    {
    }

    public function render(RenderInput $input): string
    {
        $values = $input->view->values;

        return match ($this->block)
        {
            'heading' => $this->heading($input, $values),
            'paragraph' => sprintf(
                '<p%s>%s</p>',
                $input->escaper->attributes($input->editable('text')),
                $input->escaper->richText($this->string($values, 'text')),
            ),
            'list' => $this->list($input, $values),
            'list-item' => $this->listItem($input, $values),
            'quote' => $this->quote($input, $values),
            'code' => $this->code($input, $values),
            'table' => $this->table($input, $values),
            'separator' => sprintf('<hr%s>', $input->escaper->attributes($input->attributes)),
            'spacer' => $this->spacer($input, $values),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function heading(RenderInput $input, array $values): string
    {
        $level = is_int($values['level'] ?? null) ? max(1, min(6, $values['level'])) : 2;
        $id = $this->string($values, 'id');
        $bag = $id === '' ? $input->editable('text') : $input->editable('text')->withId($id);

        return sprintf(
            '<h%1$d%2$s>%3$s</h%1$d>',
            $level,
            $input->escaper->attributes($bag),
            $input->escaper->richText($this->string($values, 'text')),
        );
    }

    /**
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function list(RenderInput $input, array $values): string
    {
        $ordered = ($values['ordered'] ?? false) === true;
        $start = is_int($values['start'] ?? null) ? $values['start'] : 1;
        $bag = $ordered && $start > 1
            ? $input->attributes->withAttribute('start', (string) $start)
            : $input->attributes;

        return sprintf(
            '<%1$s%2$s>%3$s</%1$s>',
            $ordered ? 'ol' : 'ul',
            $input->escaper->attributes($bag),
            $input->children(),
        );
    }

    /**
     * Draws a list item; while editing one that holds a list, its own text is wrapped so only that can be typed into.
     *
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function listItem(RenderInput $input, array $values): string
    {
        $text = $input->escaper->richText($this->string($values, 'text'));
        $children = $input->children();

        if ($children !== '' && $input->request->editing)
        {
            return sprintf(
                '<li%s><span%s>%s</span>%s</li>',
                $input->escaper->attributes($input->attributes),
                $input->escaper->attributes($this->mark($input, 'text')),
                $text,
                $children,
            );
        }

        $bag = $children === '' ? $input->editable('text') : $input->attributes;

        return sprintf('<li%s>%s%s</li>', $input->escaper->attributes($bag), $text, $children);
    }

    /**
     * Draws a quote; an empty citation is left out for visitors and kept, empty, while editing so it can be typed into.
     *
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function quote(RenderInput $input, array $values): string
    {
        $citation = $this->string($values, 'citation');
        $cite = $citation === '' && !$input->request->editing
            ? ''
            : sprintf(
                '<cite%s>%s</cite>',
                $input->escaper->attributes($this->mark($input, 'citation')),
                $input->escaper->text($citation),
            );

        return sprintf(
            '<blockquote%s><p%s>%s</p>%s</blockquote>',
            $input->escaper->attributes($input->attributes),
            $input->escaper->attributes($this->mark($input, 'text')),
            $input->escaper->richText($this->string($values, 'text')),
            $cite,
        );
    }

    /**
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function code(RenderInput $input, array $values): string
    {
        $language = $this->string($values, 'language');
        $inner = $this->mark($input, 'code');

        // The definition already limits the language to letters, digits and a few symbols; checked again here.
        if ($language !== '' && preg_match('/^[a-z0-9+#-]{1,32}\z/', $language) === 1)
        {
            $inner = $inner->withClass('language-' . $language);
        }

        return sprintf(
            '<pre%s><code%s>%s</code></pre>',
            $input->escaper->attributes($input->attributes),
            $input->escaper->attributes($inner),
            $input->escaper->text($this->string($values, 'code')),
        );
    }

    /**
     * Draws a table, padding short rows to the longest so every row has the same columns.
     *
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function table(RenderInput $input, array $values): string
    {
        $stored = is_array($values['rows'] ?? null) && array_is_list($values['rows']) ? $values['rows'] : [];
        $rows = array_values(array_filter($stored, static fn (mixed $row): bool => is_array($row)));
        $columns = max(1, ...array_map(static fn (array $row): int => count($row), $rows ?: [[]]));
        $header = ($values['header'] ?? true) === true && $rows !== [];
        $draw = function (array $row, int $r, string $cell, string $extra) use ($input, $columns): string {
            $out = '';

            for ($c = 0; $c < $columns; $c++)
            {
                $text = is_string($row[$c] ?? null) ? $row[$c] : '';
                $out .= sprintf(
                    '<%1$s%2$s%3$s>%4$s</%1$s>',
                    $cell,
                    $extra,
                    $input->escaper->attributes($this->mark($input, sprintf('rows.%d.%d', $r, $c))),
                    $input->escaper->richText($text),
                );
            }

            return '<tr>' . $out . '</tr>';
        };
        $head = $header ? '<thead>' . $draw($rows[0], 0, 'th', ' scope="col"') . '</thead>' : '';
        $body = '';

        foreach ($rows as $r => $row)
        {
            if (!$header || $r > 0)
            {
                $body .= $draw($row, $r, 'td', '');
            }
        }

        $caption = $this->string($values, 'caption');
        $figcaption = $caption === '' && !$input->request->editing
            ? ''
            : sprintf(
                '<figcaption%s>%s</figcaption>',
                $input->escaper->attributes($this->mark($input, 'caption')),
                $input->escaper->richText($caption),
            );

        return sprintf(
            '<figure%s><table>%s%s</table>%s</figure>',
            $input->escaper->attributes($input->attributes),
            $head,
            $body === '' ? '' : '<tbody>' . $body . '</tbody>',
            $figcaption,
        );
    }

    /**
     * Draws a spacer: an empty element whose height comes from collected style rules, so it can differ by width.
     *
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function spacer(RenderInput $input, array $values): string
    {
        $height = ResponsiveValue::from($values['height'] ?? '2rem');
        $rules = [];

        foreach (['' => $height->base(), ...$height->overrides()] as $at => $value)
        {
            // A stored value that does not fit the definition can only be there if it skipped the editor; drop it.
            if (!is_string($value) || preg_match('/' . TextTypesPackage::LENGTH . '/', $value) !== 1)
            {
                continue;
            }

            try
            {
                $rules[] = new StyleRule(['height' => $this->css($value)], $at === '' ? null : (string) $at);
            }
            catch (InvalidStyleException)
            {
                continue;
            }
        }

        $class = $this->styles->add(...$rules);
        $bag = $input->attributes->withAttribute('aria-hidden', 'true');

        return sprintf('<div%s></div>', $input->escaper->attributes($class === '' ? $bag : $bag->withClass($class)));
    }

    /**
     * Gives the marks for one setting on an inner element, without the block's own attributes, which go outside.
     *
     * @since 0.1.0
     */
    private function mark(RenderInput $input, string $setting): AttributeBag
    {
        return (new RenderInput(
            $input->blockId,
            $input->type,
            $input->view,
            AttributeBag::empty(),
            $input->childPasses,
            $input->context,
            $input->request,
            $input->escaper,
            $input->admin,
        ))->editable($setting);
    }

    /**
     * Writes a stored length as CSS, turning a `token:name` into the custom property the shared style settings use.
     *
     * @since 0.1.0
     */
    private function css(string $value): string
    {
        return str_starts_with($value, 'token:') ? 'var(--' . str_replace('.', '-', substr($value, 6)) . ')' : $value;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @since 0.1.0
     */
    private function string(array $values, string $key): string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : '';
    }
}
