<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\DeclaresAssetsInterface;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Interactive\Domain\ElementIds;

/**
 * Draws one interactive block as markup that works with no script.
 *
 * Accordions are disclosure elements, the modal is a popover, tabs are headed sections in order and slides a row
 * to scroll; the script reads the `data-` attributes here to add the rest. While editing, everything that opens is
 * drawn open and in place, so it can be typed into.
 *
 * @since 0.1.0
 */
class InteractiveOutput implements BlockOutputInterface, DeclaresAssetsInterface
{
    /**
     * @param string $block
     *   Which interactive block this output draws, by its name after `core/`.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly string $block)
    {
    }

    public function assets(): array
    {
        return [InteractivePackage::STYLESHEET, InteractivePackage::SCRIPT];
    }

    public function render(RenderInput $input): string
    {
        $values = $input->view->values;
        $e = $input->escaper;

        return match ($this->block)
        {
            'accordion' => sprintf(
                '<div%s>%s</div>',
                $e->attributes($input->attributes->withClass('b-accordion')),
                $input->children(),
            ),
            'accordion-item' => $this->accordionItem($input, $values),
            'tabs' => sprintf(
                '<div%s>%s</div>',
                $e->attributes($this->own($input, $values, 'b-tabs')->withAttribute('data-b-tabs', '')),
                $input->children(),
            ),
            'tab' => sprintf(
                '<section%s><h3%s>%s</h3><div class="b-tab-panel">%s</div></section>',
                $e->attributes($this->own($input, $values, 'b-tab')->withAttribute('data-b-tab', '')),
                $e->attributes($this->mark($input, 'label')->withClass('b-tab-label')),
                $e->text($this->string($values, 'label')),
                $input->children(),
            ),
            'slider' => $this->slider($input, $values),
            'slide' => $this->slide($input, $values),
            'countdown' => $this->countdown($input, $values),
            'progress' => $this->progress($input, $values),
            'counter' => $this->counter($input, $values),
            'modal' => $this->modal($input, $values),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function accordionItem(RenderInput $input, array $values): string
    {
        $bag = $input->attributes->withClass('b-accordion-item');
        $group = $this->string($values, 'group');

        // Editors see every item open; grouping would close the others as one opened.
        if (!$input->request->editing && $group !== '')
        {
            $bag = $bag->withAttribute('name', $group);
        }

        if ($input->request->editing || ($values['open'] ?? false) === true)
        {
            $bag = $bag->withAttribute('open', '');
        }

        return sprintf(
            '<details%s><summary%s>%s</summary><div class="b-accordion-panel">%s</div></details>',
            $input->escaper->attributes($bag),
            $input->escaper->attributes($this->mark($input, 'title')),
            $input->escaper->text($this->string($values, 'title')),
            $input->children(),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function slider(RenderInput $input, array $values): string
    {
        $interval = is_int($values['interval'] ?? null) ? max(3, min(30, $values['interval'])) : 6;
        $bag = $this->own($input, $values, 'b-slider')
            ->withAttribute('aria-roledescription', 'carousel')
            ->withAttribute('aria-label', $this->string($values, 'label'))
            ->withAttribute('data-b-slider', '')
            ->withAttribute('data-autoplay', ($values['autoplay'] ?? false) === true ? '1' : '0')
            ->withAttribute('data-interval', (string) $interval);

        return sprintf(
            '<section%s><div class="b-slides">%s</div></section>',
            $input->escaper->attributes($bag),
            $input->children(),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function slide(RenderInput $input, array $values): string
    {
        $bag = $input->attributes->withClass('b-slide')
            ->withAttribute('role', 'group')
            ->withAttribute('aria-roledescription', 'slide');
        $label = $this->string($values, 'label');

        return sprintf(
            '<div%s>%s</div>',
            $input->escaper->attributes($label === '' ? $bag : $bag->withAttribute('aria-label', $label)),
            $input->children(),
        );
    }

    /**
     * Draws a countdown: its moment in words, which needs no script, and what to say once it has passed.
     *
     * @param array<string, mixed> $values
     */
    private function countdown(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $target = $this->string($values, 'target');
        $bag = $input->attributes->withClass('b-countdown');

        if ($target === '')
        {
            return $input->request->mode !== Mode::Admin ? '' : sprintf(
                '<div%s><p class="b-prompt">%s</p></div>',
                $e->attributes($bag),
                $e->text('Choose a date and time in the block\'s settings.'),
            );
        }

        $ended = ($values['ended'] ?? false) === true;
        $after = AttributeBag::empty()->withClass('b-countdown-after');
        $after = $ended || $input->request->editing ? $after : $after->withAttribute('hidden', '');
        $time = $ended && !$input->request->editing ? '' : sprintf(
            '<p class="b-countdown-time"><time%s>%s</time></p>',
            $e->attributes(AttributeBag::empty()->withAttribute('datetime', $target)),
            $e->text($this->string($values, 'when')),
        );

        return sprintf(
            '<div%s><p%s>%s</p>%s<div%s>%s</div></div>',
            $e->attributes($bag->withAttribute('data-b-countdown', '')->withAttribute('data-target', $target)),
            $e->attributes($this->mark($input, 'label')->withClass('b-countdown-label')),
            $e->text($this->string($values, 'label')),
            $time,
            $e->attributes($this->markOnto($input, 'after', $after)),
            $e->richText($this->string($values, 'after')),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function progress(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $label = ElementIds::for($input->blockId, 'label');
        $percent = (is_int($values['percent'] ?? null) ? $values['percent'] : 0) . '%';

        return sprintf(
            '<div%s><span%s>%s</span><progress%s>%s</progress><span class="b-progress-value">%s</span></div>',
            $e->attributes($input->attributes->withClass('b-progress')),
            $e->attributes($this->mark($input, 'label')->withId($label)->withClass('b-progress-label')),
            $e->text($this->string($values, 'label')),
            $e->attributes(AttributeBag::empty()
                ->withAttribute('max', $this->number($values['max'] ?? 100))
                ->withAttribute('value', $this->number($values['value'] ?? 0))
                ->withAttribute('aria-labelledby', $label)),
            $e->text($percent),
            $e->text($percent),
        );
    }

    /**
     * Draws a counter at its final value: a script counts up to it for those who see it, and screen readers always
     * hear the final value once.
     *
     * @param array<string, mixed> $values
     */
    private function counter(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $bag = $input->attributes->withClass('b-counter')->withAttribute('data-b-counter', '');

        foreach (['from', 'to', 'duration', 'decimals'] as $key)
        {
            $bag = $bag->withAttribute('data-' . $key, $this->number($values[$key] ?? 0));
        }

        $bag = $bag->withAttribute('data-prefix', $this->string($values, 'prefix'))
            ->withAttribute('data-suffix', $this->string($values, 'suffix'));
        $text = $e->text($this->string($values, 'text'));

        return sprintf(
            '<p%s><span class="b-counter-number" aria-hidden="true">%s</span>'
                . '<span class="b-visually-hidden">%s</span></p>',
            $e->attributes($bag),
            $text,
            $text,
        );
    }

    /**
     * Draws a modal as the browser's own popover, which opens, closes on Escape and returns focus with no script.
     *
     * @param array<string, mixed> $values
     */
    private function modal(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $panel = $this->string($values, 'id');
        $title = ElementIds::for($input->blockId, 'title');
        $bag = AttributeBag::empty()->withId($panel)->withClass('b-modal-panel');
        $bag = $input->request->editing ? $bag : $bag->withAttribute('popover', '');
        $bag = $bag->withAttribute('role', 'dialog')->withAttribute('aria-labelledby', $title);
        $button = fn (string $class, array $extra): string => $e->attributes(array_reduce(
            array_keys($extra),
            static fn (AttributeBag $carry, string $name): AttributeBag => $carry->withAttribute($name, $extra[$name]),
            AttributeBag::empty()->withAttribute('type', 'button')->withClass($class),
        ));

        return sprintf(
            '<div%s><button%s>%s</button><div%s><h2%s>%s</h2>%s<button%s>Close</button></div></div>',
            $e->attributes($input->attributes->withClass('b-modal')),
            $button('b-modal-open', ['popovertarget' => $panel]),
            $e->text($this->string($values, 'trigger')),
            $e->attributes($bag),
            $e->attributes($this->mark($input, 'title')->withId($title)),
            $e->text($this->string($values, 'title')),
            $input->children(),
            $button('b-modal-close', ['popovertarget' => $panel, 'popovertargetaction' => 'hide']),
        );
    }

    /**
     * The block's own attributes with its element id and class, for the blocks whose parts point at each other.
     *
     * @param array<string, mixed> $values
     */
    private function own(RenderInput $input, array $values, string $class): AttributeBag
    {
        $id = $this->string($values, 'id');
        $bag = $input->attributes->withClass($class);

        return $id === '' ? $bag : $bag->withId($id);
    }

    /**
     * Gives the marks for one setting on an inner element, without the block's own attributes, which go outside.
     */
    private function mark(RenderInput $input, string $setting): AttributeBag
    {
        return $this->markOnto($input, $setting, AttributeBag::empty());
    }

    private function markOnto(RenderInput $input, string $setting, AttributeBag $bag): AttributeBag
    {
        return (new RenderInput(
            $input->blockId,
            $input->type,
            $input->view,
            $bag,
            $input->childPasses,
            $input->context,
            $input->request,
            $input->escaper,
            $input->admin,
        ))->editable($setting);
    }

    private function number(mixed $value): string
    {
        return is_int($value) || is_float($value) ? (string) $value : '0';
    }

    /**
     * @param array<string, mixed> $values
     */
    private function string(array $values, string $key): string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : '';
    }
}
