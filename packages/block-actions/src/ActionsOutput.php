<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\DeclaresAssetsInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\ResponsiveValue;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * Draws one actions block as plain, accessible HTML styled by the `core/actions` stylesheet.
 *
 * Links go through the attribute bag, which refuses any that could run script. A button or social link with no
 * usable link is not set up: visitors get nothing rather than a link that goes nowhere, and editors see it greyed
 * out with what it needs.
 *
 * @since 0.1.0
 */
class ActionsOutput implements BlockOutputInterface, DeclaresAssetsInterface
{
    private const string NEEDS_LINK = 'Add a link in the block\'s settings.';

    /**
     * Where each of a star's ten points lies, on a 24-unit grid, and the left half of the same star.
     */
    private const string STAR = '12,2 14.9,8.6 22,9.3 16.6,14 18.2,21 12,17.3 5.8,21 7.4,14 2,9.3 9.1,8.6';

    private const string HALF_STAR = '12,2 9.1,8.6 2,9.3 7.4,14 5.8,21 12,17.3';

    /**
     * @param string $block
     *   Which actions block this output draws, by its name after `core/`.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly string $block,
        private readonly StyleCollectorInterface $styles,
    )
    {
    }

    public function assets(): array
    {
        return [ActionsPackage::STYLESHEET];
    }

    public function render(RenderInput $input): string
    {
        $values = $input->view->values;

        return match ($this->block)
        {
            'button' => $this->button($input, $values),
            'buttons' => sprintf(
                '<div%s>%s</div>',
                $input->escaper->attributes($input->attributes->withClass(
                    'b-buttons',
                    'b-buttons--' . $this->choice($values, 'align', ['start', 'center', 'end', 'between']),
                )),
                $input->children(),
            ),
            'call-to-action' => $this->callToAction($input, $values),
            'testimonial' => $this->testimonial($input, $values),
            'pricing-table' => $this->pricingTable($input, $values),
            'pricing-plan' => $this->pricingPlan($input, $values),
            'rating' => $this->rating($input, $values),
            'social-links' => sprintf(
                '<ul%s>%s</ul>',
                $input->escaper->attributes($input->attributes->withClass(
                    'b-social',
                    'b-social--' . $this->choice($values, 'size', ['medium', 'small', 'large']),
                )),
                $input->children(),
            ),
            'social-link' => $this->socialLink($input, $values),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function button(RenderInput $input, array $values): string
    {
        $link = $this->string($values, 'link');
        $classes = [
            'b-button',
            'b-button--' . $this->choice($values, 'variant', ['primary', 'secondary', 'outline', 'plain']),
            'b-button--' . $this->choice($values, 'size', ['medium', 'small', 'large']),
        ];
        $text = $input->escaper->text($this->string($values, 'text'));

        if (!$this->usable($input, $link))
        {
            // The note sits below the greyed-out button, in the page's own colours, so it can be read on any button.
            return $input->request->mode !== Mode::Admin ? '' : sprintf(
                '<span%s><span%s><span%s>%s</span></span>%s</span>',
                $input->escaper->attributes($input->attributes->withClass('b-unset')),
                $input->escaper->attributes(
                    AttributeBag::empty()->withClass(...$classes)->withAttribute('aria-disabled', 'true'),
                ),
                $input->escaper->attributes($this->mark($input, 'text')),
                $text,
                $this->note(),
            );
        }

        $bag = $input->editable('text')->withClass(...$classes)->withAttribute('href', $link);
        $bag = ($values['newTab'] ?? false) === true
            ? $bag->withAttribute('target', '_blank')->withAttribute('rel', 'noopener noreferrer')
            : $bag;

        return sprintf('<a%s>%s</a>', $input->escaper->attributes($bag), $text);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function callToAction(RenderInput $input, array $values): string
    {
        $level = is_int($values['level'] ?? null) ? max(2, min(4, $values['level'])) : 2;
        $text = $this->string($values, 'text');

        return sprintf(
            '<section%1$s><h%2$d%3$s>%4$s</h%2$d>%5$s%6$s</section>',
            $input->escaper->attributes($input->attributes->withClass('b-cta')),
            $level,
            $input->escaper->attributes($this->mark($input, 'heading')),
            $input->escaper->text($this->string($values, 'heading')),
            $text === '' && !$input->request->editing
                ? ''
                : sprintf(
                    '<div%s>%s</div>',
                    $input->escaper->attributes($this->mark($input, 'text')->withClass('b-cta-text')),
                    $input->escaper->richText($text),
                ),
            $input->children(),
        );
    }

    /**
     * Draws a testimonial; the photo is decoration, since the name beside it says who it is.
     *
     * @param array<string, mixed> $values
     */
    private function testimonial(RenderInput $input, array $values): string
    {
        $photo = is_array($values['photo'] ?? null) ? $values['photo'] : null;
        $image = '';

        if ($photo !== null && is_string($photo['url'] ?? null))
        {
            $bag = AttributeBag::empty()->withAttribute('src', $photo['url']);

            foreach (['width', 'height'] as $side)
            {
                $bag = is_int($photo[$side] ?? null) ? $bag->withAttribute($side, (string) $photo[$side]) : $bag;
            }

            $image = sprintf('<img%s>', $input->escaper->attributes(
                $bag->withAttribute('alt', '')->withAttribute('loading', 'lazy'),
            ));
        }

        return sprintf(
            '<figure%s><blockquote%s>%s</blockquote><figcaption>%s%s%s</figcaption></figure>',
            $input->escaper->attributes($input->attributes->withClass('b-testimonial')),
            $input->escaper->attributes($this->mark($input, 'quote')),
            $input->escaper->richText($this->string($values, 'quote')),
            $image,
            $this->part($input, $values, 'name', 'span', 'b-testimonial-name'),
            $this->part($input, $values, 'role', 'span', 'b-testimonial-role'),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function pricingTable(RenderInput $input, array $values): string
    {
        $class = $this->styles->add(...$this->columns($values['columns'] ?? 3));
        $bag = $input->attributes->withClass('b-pricing');

        return sprintf(
            '<div%s>%s</div>',
            $input->escaper->attributes($class === '' ? $bag : $bag->withClass($class)),
            $input->children(),
        );
    }

    /**
     * Draws one plan; an empty feature is left out for visitors and kept while editing, so it can be typed into.
     *
     * @param array<string, mixed> $values
     */
    private function pricingPlan(RenderInput $input, array $values): string
    {
        $bag = $input->attributes->withClass('b-plan');
        $bag = ($values['highlighted'] ?? false) === true ? $bag->withClass('b-plan--highlighted') : $bag;
        $features = '';

        foreach (is_array($values['features'] ?? null) ? array_values($values['features']) : [] as $at => $feature)
        {
            if (!is_string($feature) || ($feature === '' && !$input->request->editing))
            {
                continue;
            }

            $features .= sprintf(
                '<li%s>%s</li>',
                $input->escaper->attributes($this->mark($input, 'features.' . $at)),
                $input->escaper->text($feature),
            );
        }

        $description = $this->string($values, 'description');

        return sprintf(
            '<article%s>%s<h3%s>%s</h3><p class="b-plan-price">%s%s</p>%s%s%s</article>',
            $input->escaper->attributes($bag),
            $this->part($input, $values, 'badge', 'p', 'b-plan-badge', false),
            $input->escaper->attributes($this->mark($input, 'name')),
            $input->escaper->text($this->string($values, 'name')),
            $this->part($input, $values, 'price', 'span', ''),
            $this->string($values, 'period') === '' && !$input->request->editing
                ? ''
                : ' ' . $this->part($input, $values, 'period', 'span', ''),
            $description === '' && !$input->request->editing ? '' : sprintf(
                '<div%s>%s</div>',
                $input->escaper->attributes($this->mark($input, 'description')->withClass('b-plan-description')),
                $input->escaper->richText($description),
            ),
            $features === '' ? '' : '<ul class="b-plan-features">' . $features . '</ul>',
            $input->children(),
        );
    }

    /**
     * Draws a rating as words, which everyone gets, with stars beside them as decoration.
     *
     * @param array<string, mixed> $values
     */
    private function rating(RenderInput $input, array $values): string
    {
        $outOf = is_int($values['outOf'] ?? null) ? max(1, min(10, $values['outOf'])) : 5;
        $stored = $values['value'] ?? null;
        $value = is_int($stored) || is_float($stored) ? (float) $stored : 0.0;
        $stars = '';

        for ($star = 1; $star <= $outOf; $star++)
        {
            $fill = $value >= $star ? 'full' : ($value >= $star - 0.5 ? 'half' : 'empty');
            $stars .= $this->star($fill);
        }

        return sprintf(
            '<p%s><span class="b-rating-stars" aria-hidden="true">%s</span><span class="b-rating-text">%s</span></p>',
            $input->escaper->attributes($input->attributes->withClass('b-rating')),
            $stars,
            $input->escaper->text($this->string($values, 'text')),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function socialLink(RenderInput $input, array $values): string
    {
        $url = $this->string($values, 'url');
        $title = $input->escaper->text($this->string($values, 'title'));

        if (!$this->usable($input, $url))
        {
            return $input->request->mode !== Mode::Admin ? '' : sprintf(
                '<li%s><span class="b-social-link" aria-disabled="true">%s</span>%s</li>',
                $input->escaper->attributes($input->attributes),
                $title,
                $this->note(),
            );
        }

        return sprintf(
            '<li%s><a%s>%s</a></li>',
            $input->escaper->attributes($input->attributes),
            $input->escaper->attributes(AttributeBag::empty()
                ->withClass('b-social-link')
                ->withAttribute('href', $url)
                ->withAttribute('rel', 'me noopener')),
            $title,
        );
    }

    /**
     * Tells whether a link can be followed: given, and accepted by the escaper's link check.
     */
    private function usable(RenderInput $input, string $link): bool
    {
        return trim($link) !== '' && $input->escaper->url($link) !== '';
    }

    private function note(): string
    {
        return '<span class="b-note">' . self::NEEDS_LINK . '</span>';
    }

    /**
     * Draws one short text setting in its own element; an empty one is left out for visitors and, when it can be
     * typed into, kept while editing.
     *
     * @param array<string, mixed> $values
     */
    private function part(
        RenderInput $input,
        array $values,
        string $setting,
        string $tag,
        string $class,
        bool $typed = true,
    ): string
    {
        $text = $this->string($values, $setting);

        if ($text === '' && (!$input->request->editing || !$typed))
        {
            return '';
        }

        $bag = $typed ? $this->mark($input, $setting) : AttributeBag::empty();

        return sprintf(
            '<%1$s%2$s>%3$s</%1$s>',
            $tag,
            $input->escaper->attributes($class === '' ? $bag : $bag->withClass($class)),
            $input->escaper->text($text),
        );
    }

    private function star(string $fill): string
    {
        $shape = match ($fill)
        {
            'full' => sprintf('<polygon points="%s" fill="currentColor"/>', self::STAR),
            'half' => sprintf(
                '<polygon points="%s" fill="currentColor"/><polygon points="%s" fill="none" stroke="currentColor"'
                    . ' stroke-width="1.5" stroke-linejoin="round"/>',
                self::HALF_STAR,
                self::STAR,
            ),
            default => sprintf(
                '<polygon points="%s" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
                self::STAR,
            ),
        };

        return sprintf(
            '<svg class="b-star b-star--%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">'
                . '%s</svg>',
            $fill,
            $shape,
        );
    }

    /**
     * Lays the plans out in a grid with a number of columns that may differ by breakpoint, from 1 to 4.
     *
     * @return list<StyleRule>
     */
    private function columns(mixed $stored): array
    {
        $value = ResponsiveValue::from($stored);
        $declare = static fn (mixed $count): array => [
            'grid-template-columns' => sprintf(
                'repeat(%d, minmax(0, 1fr))',
                is_int($count) ? max(1, min(4, $count)) : 3,
            ),
        ];

        try
        {
            $rules = [new StyleRule(['display' => 'grid', ...$declare($value->base())])];

            foreach ($value->overrides() as $breakpoint => $count)
            {
                $rules[] = new StyleRule($declare($count), (string) $breakpoint);
            }

            return $rules;
        }
        catch (InvalidStyleException)
        {
            return [];
        }
    }

    /**
     * Gives the marks for one setting on an inner element, without the block's own attributes, which go outside.
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
     * Reads a setting that must be one of a fixed list, falling back to the list's first entry.
     *
     * @param array<string, mixed> $values
     * @param list<string> $options
     */
    private function choice(array $values, string $key, array $options): string
    {
        $value = $values[$key] ?? null;

        return in_array($value, $options, true) ? $value : $options[0];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function string(array $values, string $key): string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : '';
    }
}
