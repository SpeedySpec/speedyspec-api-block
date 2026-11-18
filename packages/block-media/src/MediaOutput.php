<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\ResponsiveValue;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * Draws one media block as plain, accessible HTML.
 *
 * Every address goes through the attribute bag, which refuses links that could run script. Other sites are only
 * ever framed sandboxed, with a plain link after the frame for visitors who cannot or will not load it. A block not
 * set up yet draws nothing for visitors and a short prompt for editors.
 *
 * @since 0.1.0
 */
class MediaOutput implements BlockOutputInterface
{
    private const string SANDBOX = 'allow-scripts allow-same-origin allow-presentation allow-popups';

    private const array PROMPTS = [
        'image' => 'Choose an image in the block\'s settings.',
        'gallery' => 'Choose images in the block\'s settings.',
        'video' => 'Choose a video in the block\'s settings.',
        'audio' => 'Choose an audio file in the block\'s settings.',
        'embed' => 'Paste a YouTube or Vimeo link in the block\'s settings.',
        'map' => 'Give an address or coordinates in the block\'s settings.',
    ];

    /**
     * @param string $block
     *   Which media block this output draws, by its name after `core/`.
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
            'image' => $this->image($input, $values),
            'gallery' => $this->gallery($input, $values),
            'video', 'audio' => $this->player($input, $values),
            'embed' => $this->embed($input, $values),
            'map' => $this->map($input, $values),
            'icon' => $this->icon($input, $values),
            'icon-list' => sprintf(
                '<ul%s>%s</ul>',
                $input->escaper->attributes($input->attributes->withClass('b-icon-list')),
                $input->children(),
            ),
            'icon-list-item' => $this->iconListItem($input, $values),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function image(RenderInput $input, array $values): string
    {
        $url = $this->string($values, 'url');

        if ($url === '')
        {
            return $this->prompt($input);
        }

        $image = $this->img($input, $url, $values, $this->string($values, 'srcset'));
        $link = $this->string($values, 'link');

        if ($link !== '' && $input->escaper->url($link) !== '')
        {
            $image = sprintf(
                '<a%s>%s</a>',
                $input->escaper->attributes(AttributeBag::empty()->withAttribute('href', $link)),
                $image,
            );
        }

        return $this->figure($input, $image, $values);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function gallery(RenderInput $input, array $values): string
    {
        $items = is_array($values['items'] ?? null) ? $values['items'] : [];

        if ($items === [])
        {
            return $this->prompt($input);
        }

        $list = '';

        foreach ($items as $item)
        {
            if (is_array($item) && is_string($item['url'] ?? null))
            {
                $list .= '<li>' . $this->img($input, $item['url'], $item, '') . '</li>';
            }
        }

        $class = $this->styles->add(...$this->columns($values['columns'] ?? 3));
        $bag = $class === '' ? AttributeBag::empty() : AttributeBag::empty()->withClass($class);

        return $this->figure($input, sprintf('<ul%s>%s</ul>', $input->escaper->attributes($bag), $list), $values);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function player(RenderInput $input, array $values): string
    {
        $url = $this->string($values, 'url');

        if ($url === '')
        {
            return $this->prompt($input);
        }

        $autoplay = ($values['autoplay'] ?? false) === true;
        $bag = AttributeBag::empty()->withAttribute('src', $url);

        $flags = ['controls' => $values['controls'] ?? false, 'autoplay' => $autoplay, 'muted' => $autoplay];

        foreach ($flags as $flag => $on)
        {
            $bag = $on === true ? $bag->withAttribute($flag, '') : $bag;
        }

        $bag = ($values['loop'] ?? false) === true ? $bag->withAttribute('loop', '') : $bag;
        $bag = $autoplay ? $bag->withAttribute('playsinline', '') : $bag;
        $poster = $this->string($values, 'poster');
        $bag = $poster !== '' && $this->block === 'video' ? $bag->withAttribute('poster', $poster) : $bag;
        $bag = $bag->withAttribute('preload', 'metadata');

        return $this->figure($input, sprintf(
            '<%1$s%2$s><a%3$s>%4$s</a></%1$s>',
            $this->block,
            $input->escaper->attributes($bag),
            $input->escaper->attributes(AttributeBag::empty()->withAttribute('href', $url)),
            $this->block === 'video' ? 'Download the video' : 'Download the audio file',
        ), $values);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function embed(RenderInput $input, array $values): string
    {
        $src = $this->string($values, 'src');

        if ($src === '')
        {
            return $this->prompt($input);
        }

        $title = $this->string($values, 'title');

        return $this->figure(
            $input,
            $this->frame($input, $src, $title, true) . $this->link($input, $this->string($values, 'link'), $title),
            $values,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function map(RenderInput $input, array $values): string
    {
        $src = $this->string($values, 'src');
        $link = $this->string($values, 'link');

        if ($src === '' && $link === '')
        {
            return $this->prompt($input);
        }

        $title = $this->string($values, 'title');

        if ($src === '')
        {
            return $this->figure($input, $this->link($input, $link, $this->string($values, 'address')), []);
        }

        $content = $this->frame($input, $src, $title, false) . $this->link($input, $link, $title);

        return $this->figure($input, $content, []);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function icon(RenderInput $input, array $values): string
    {
        $name = $this->string($values, 'name');
        $label = $this->string($values, 'label');
        $svg = Icons::svg($name);

        if ($svg === null)
        {
            // An editor is told which name the set lacks; a visitor gets nothing rather than a stray word.
            return $input->request->mode === Mode::Admin
                ? sprintf(
                    '<span%s>%s</span>',
                    $input->escaper->attributes($input->attributes),
                    $input->escaper->text($name),
                )
                : '';
        }

        $bag = $input->attributes->withClass('b-icon');
        $bag = $label === ''
            ? $bag->withAttribute('aria-hidden', 'true')
            : $bag->withAttribute('role', 'img')->withAttribute('aria-label', $label);

        return sprintf('<span%s>%s</span>', $input->escaper->attributes($bag), $svg);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function iconListItem(RenderInput $input, array $values): string
    {
        $svg = Icons::svg($this->string($values, 'icon'));

        return sprintf(
            '<li%s>%s<span%s>%s</span></li>',
            $input->escaper->attributes($input->attributes),
            $svg === null ? '' : '<span class="b-icon" aria-hidden="true">' . $svg . '</span>',
            $input->escaper->attributes($this->mark($input, 'text')),
            $input->escaper->richText($this->string($values, 'text')),
        );
    }

    /**
     * Draws an image element; its size is given when known so the page does not jump as it loads.
     *
     * @param array<string, mixed> $values
     */
    private function img(RenderInput $input, string $url, array $values, string $srcset): string
    {
        $bag = AttributeBag::empty()->withAttribute('src', $url);

        if ($srcset !== '')
        {
            $bag = $bag->withAttribute('srcset', $srcset)->withAttribute('sizes', '100vw');
        }

        foreach (['width', 'height'] as $side)
        {
            $bag = is_int($values[$side] ?? null) ? $bag->withAttribute($side, (string) $values[$side]) : $bag;
        }

        $bag = $bag->withAttribute('alt', $this->string($values, 'alt'))
            ->withAttribute('loading', 'lazy')
            ->withAttribute('decoding', 'async');

        return sprintf('<img%s>', $input->escaper->attributes($bag));
    }

    /**
     * Frames another site's page, sandboxed, lazily and without telling it more than the site's address.
     */
    private function frame(RenderInput $input, string $src, string $title, bool $fullscreen): string
    {
        $bag = AttributeBag::empty()
            ->withAttribute('src', $src)
            ->withAttribute('title', $title)
            ->withAttribute('sandbox', self::SANDBOX)
            ->withAttribute('loading', 'lazy')
            ->withAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        $bag = $fullscreen ? $bag->withAttribute('allowfullscreen', '') : $bag;

        return sprintf('<iframe%s></iframe>', $input->escaper->attributes($bag));
    }

    private function link(RenderInput $input, string $href, string $text): string
    {
        return sprintf(
            '<a%s>%s</a>',
            $input->escaper->attributes(AttributeBag::empty()->withAttribute('href', $href)),
            $input->escaper->text($text),
        );
    }

    /**
     * Wraps a block's content in its figure; a caption is drawn when there is one, and while editing so it can be
     * typed into.
     *
     * @param array<string, mixed> $values
     */
    private function figure(RenderInput $input, string $content, array $values): string
    {
        $caption = $this->string($values, 'caption');
        $hasCaption = array_key_exists('caption', $values) && ($caption !== '' || $input->request->editing);

        return sprintf(
            '<figure%s>%s%s</figure>',
            $input->escaper->attributes($input->attributes),
            $content,
            $hasCaption
                ? sprintf(
                    '<figcaption%s>%s</figcaption>',
                    $input->escaper->attributes($this->mark($input, 'caption')),
                    $input->escaper->richText($caption),
                )
                : '',
        );
    }

    /**
     * Says what a block not set up yet needs, to editors only.
     */
    private function prompt(RenderInput $input): string
    {
        if ($input->request->mode !== Mode::Admin)
        {
            return '';
        }

        return sprintf(
            '<figure%s><p class="b-media-empty">%s</p></figure>',
            $input->escaper->attributes($input->attributes),
            $input->escaper->text(self::PROMPTS[$this->block] ?? ''),
        );
    }

    /**
     * Lays a gallery out in a grid with a number of columns that may differ by breakpoint, from 1 to 6.
     *
     * @return list<StyleRule>
     */
    private function columns(mixed $stored): array
    {
        $value = ResponsiveValue::from($stored);
        $declare = static fn (mixed $count): array => [
            'grid-template-columns' => sprintf(
                'repeat(%d, minmax(0, 1fr))',
                is_int($count) ? max(1, min(6, $count)) : 3,
            ),
        ];

        try
        {
            $rules = [new StyleRule([
                'display' => 'grid',
                'gap' => '1rem',
                'list-style' => 'none',
                'margin' => '0',
                'padding' => '0',
                ...$declare($value->base()),
            ])];

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
     * @param array<string, mixed> $values
     */
    private function string(array $values, string $key): string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : '';
    }
}
