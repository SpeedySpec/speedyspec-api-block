<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Presentation;

use SpeedySpec\Block\Domain\Contracts\RenderStepInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

/**
 * Gives a styled block its class while it is drawn, and hands its rules to the collector.
 *
 * It works through the attribute bag, so every block type gets the shared style settings without its output
 * knowing about them.
 *
 * @since 0.1.0
 */
class StyleStep implements RenderStepInterface
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly StyleCollectorInterface $styles)
    {
    }

    public function attributes(AttributeBag $bag, ResolvedBlock $block, RenderRequest $request): AttributeBag
    {
        $settings = $block->extensions[StyleVocabulary::NAMESPACE] ?? [];

        // A stylesheet only means something to a page; another target gets the block without its styles.
        if ($request->target !== 'html' || !is_array($settings))
        {
            return $bag;
        }

        $rules = StyleVocabulary::rules($settings);

        return $rules === [] ? $bag : $bag->withClass($this->styles->add(...$rules));
    }
}
