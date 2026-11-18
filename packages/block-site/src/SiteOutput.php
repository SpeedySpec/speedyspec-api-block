<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\DeclaresAssetsInterface;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * Draws one site block as plain, accessible HTML.
 *
 * Every address goes through the attribute bag, which refuses any that could run script; a link it refuses is shown
 * as text rather than dropped, so a menu or trail keeps its shape. A block with nothing to show draws nothing for
 * visitors and a short prompt for editors.
 *
 * @since 0.1.0
 */
class SiteOutput implements BlockOutputInterface, DeclaresAssetsInterface
{
    /**
     * @param string $block
     *   Which site block this output draws, by its name after `core/`.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly string $block)
    {
    }

    public function assets(): array
    {
        return [SitePackage::STYLESHEET];
    }

    public function render(RenderInput $input): string
    {
        $values = $input->view->values;

        return match ($this->block)
        {
            'navigation' => $this->navigation($input, $values),
            'navigation-link' => $this->navigationLink($input, $values),
            'table-of-contents' => $this->tableOfContents($input, $values),
            'breadcrumbs' => $this->breadcrumbs($input, $values),
            'search' => $this->search($input, $values),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function navigation(RenderInput $input, array $values): string
    {
        $orientation = ($values['orientation'] ?? '') === 'vertical' ? 'vertical' : 'horizontal';
        $label = trim($this->string($values, 'label'));

        return sprintf(
            '<nav%s><ul>%s</ul></nav>',
            $input->escaper->attributes($input->attributes
                ->withClass('b-nav', 'b-nav--' . $orientation)
                ->withAttribute('aria-label', $label === '' ? 'Main' : $label)),
            $input->children(),
        );
    }

    /**
     * Draws a menu link; one without an address still opens its submenu, and one the escaper refuses is text.
     *
     * @param array<string, mixed> $values
     */
    private function navigationLink(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $label = $this->string($values, 'label');
        $url = $this->string($values, 'url');
        $children = $input->children();
        $usable = $url !== '' && $e->url($url) !== '';
        $item = $input->attributes->withClass('b-nav-item');
        $item = $children === '' ? $item : $item->withClass('b-nav-item--has-sub');
        $submenu = $children === '' ? '' : '<ul class="b-nav-sub">' . $children . '</ul>';

        if (trim($label) === '' || ($url === '' && $children === ''))
        {
            return $input->request->mode !== Mode::Admin ? '' : sprintf(
                '<li%s><span%s>%s</span><span class="b-note">%s</span>%s</li>',
                $e->attributes($item),
                $e->attributes(
                    $this->mark($input, 'label')->withClass('b-nav-text')->withAttribute('aria-disabled', 'true'),
                ),
                $e->text($label),
                $e->text('Add a link in the block\'s settings.'),
                $submenu,
            );
        }

        if (!$usable)
        {
            return sprintf(
                '<li%s><span%s>%s</span>%s</li>',
                $e->attributes($item),
                $e->attributes($this->mark($input, 'label')->withClass('b-nav-text')),
                $e->text($label),
                $submenu,
            );
        }

        $link = $this->mark($input, 'label')->withAttribute('href', $url);
        $link = ($values['current'] ?? false) === true ? $link->withAttribute('aria-current', 'page') : $link;
        $link = ($values['newTab'] ?? false) === true
            ? $link->withAttribute('target', '_blank')->withAttribute('rel', 'noopener noreferrer')
            : $link;

        return sprintf(
            '<li%s><a%s>%s</a>%s</li>',
            $e->attributes($item),
            $e->attributes($link),
            $e->text($label),
            $submenu,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function tableOfContents(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $items = is_array($values['items'] ?? null) ? $values['items'] : [];
        $bag = $input->attributes->withClass('b-toc');

        if ($items === [])
        {
            return $this->prompt($input, $bag, 'nav', 'Add headings to the page to list them here.');
        }

        $title = $this->id($input->blockId, 'title');
        $list = '';

        foreach ($items as $item)
        {
            $level = is_int($item['level'] ?? null) ? max(1, min(6, $item['level'])) : 2;
            $list .= sprintf(
                '<li class="b-toc-l%d"><a%s>%s</a></li>',
                $level,
                $e->attributes(AttributeBag::empty()->withAttribute('href', '#' . (string) ($item['id'] ?? ''))),
                $e->text(is_string($item['text'] ?? null) ? $item['text'] : ''),
            );
        }

        return sprintf(
            '<nav%s><h2%s>%s</h2><ol>%s</ol></nav>',
            $e->attributes($bag->withAttribute('aria-labelledby', $title)),
            $e->attributes($this->mark($input, 'title')->withId($title)),
            $e->text($this->string($values, 'title')),
            $list,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function breadcrumbs(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $crumbs = is_array($values['crumbs'] ?? null) ? $values['crumbs'] : [];
        $label = trim($this->string($values, 'label'));
        $bag = $input->attributes->withClass('b-crumbs')
            ->withAttribute('aria-label', $label === '' ? 'Breadcrumb' : $label);

        if ($crumbs === [])
        {
            return $this->prompt($input, $bag, 'nav', 'The site gives this page no trail.');
        }

        $list = '';

        foreach ($crumbs as $crumb)
        {
            $text = $e->text(is_string($crumb['label'] ?? null) ? $crumb['label'] : '');
            $url = is_string($crumb['url'] ?? null) ? $crumb['url'] : '';

            $list .= match (true)
            {
                ($crumb['current'] ?? false) === true => '<li><span aria-current="page">' . $text . '</span></li>',
                $url !== '' && $e->url($url) !== '' => sprintf(
                    '<li><a%s>%s</a></li>',
                    $e->attributes(AttributeBag::empty()->withAttribute('href', $url)),
                    $text,
                ),
                default => '<li><span>' . $text . '</span></li>',
            };
        }

        return sprintf('<nav%s><ol>%s</ol></nav>', $e->attributes($bag), $list);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function search(RenderInput $input, array $values): string
    {
        $e = $input->escaper;
        $url = $this->string($values, 'url');
        $bag = $input->attributes->withClass('b-search');

        if ($url === '' || $e->url($url) === '' || $this->string($values, 'parameter') === '')
        {
            return $this->prompt($input, $bag, 'div', 'This site has no search to send to.');
        }

        $id = $this->string($values, 'id');
        $field = AttributeBag::empty()->withId($id)
            ->withAttribute('type', 'search')
            ->withAttribute('name', $this->string($values, 'parameter'));
        $placeholder = $this->string($values, 'placeholder');
        $field = $placeholder === '' ? $field : $field->withAttribute('placeholder', $placeholder);

        return sprintf(
            '<form%s><label%s>%s</label><input%s><button%s>%s</button></form>',
            $e->attributes($bag
                ->withAttribute('role', 'search')
                ->withAttribute('method', 'get')
                ->withAttribute('action', $url)),
            $e->attributes($this->mark($input, 'label')->withAttribute('for', $id)),
            $e->text($this->string($values, 'label')),
            $e->attributes($field),
            $e->attributes($this->mark($input, 'button')->withAttribute('type', 'submit')),
            $e->text($this->string($values, 'button')),
        );
    }

    /**
     * Says what a block with nothing to show needs, to editors only.
     */
    private function prompt(RenderInput $input, AttributeBag $bag, string $tag, string $message): string
    {
        return $input->request->mode !== Mode::Admin ? '' : sprintf(
            '<%1$s%2$s><p class="b-prompt">%3$s</p></%1$s>',
            $tag,
            $input->escaper->attributes($bag),
            $input->escaper->text($message),
        );
    }

    /**
     * Makes an element id from a block id; anything that could not sit in an id is written as `_` and its byte.
     */
    private function id(string $blockId, string $suffix): string
    {
        return 'b-' . preg_replace_callback(
            '/[^A-Za-z0-9-]/',
            static fn (array $match): string => '_' . bin2hex($match[0]),
            $blockId,
        ) . '-' . $suffix;
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
