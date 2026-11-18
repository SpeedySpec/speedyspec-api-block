<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Points a search form where the host says search goes, and gives its field a label and an id of its own.
 *
 * A host with no search, or one whose field name could not be a field name, leaves the form not set up.
 *
 * @since 0.1.0
 */
class SearchBehaviour implements BlockBehaviourInterface
{
    private const string PARAMETER = '/^[A-Za-z][A-Za-z0-9_-]{0,31}$/D';

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $text = static fn (string $key): string => is_string($settings[$key] ?? null) ? $settings[$key] : '';
        $target = $context->has(SitePageStep::SEARCH) ? $context->get(SitePageStep::SEARCH) : null;
        $url = is_array($target) && is_string($target['url'] ?? null) ? $target['url'] : '';
        $parameter = is_array($target) && is_string($target['parameter'] ?? null) ? $target['parameter'] : '';

        if (preg_match(self::PARAMETER, $parameter) !== 1)
        {
            $url = '';
        }

        return new BlockView([
            'label' => trim($text('label')) === '' ? 'Search' : $text('label'),
            'placeholder' => $text('placeholder'),
            'button' => trim($text('button')) === '' ? 'Search' : $text('button'),
            'url' => $url,
            'parameter' => $url === '' ? '' : $parameter,
            'id' => 'b-' . preg_replace_callback(
                '/[^A-Za-z0-9-]/',
                static fn (array $match): string => '_' . bin2hex($match[0]),
                $block->id,
            ) . '-search',
        ]);
    }
}
