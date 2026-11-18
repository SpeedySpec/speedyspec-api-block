<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Decides how a map block is shown: embedded around its coordinates, or as a search link for its address.
 *
 * An address alone is not placed on a map, because finding its coordinates would mean sending it to an outside
 * service while the page is drawn.
 *
 * @since 0.1.0
 */
class MapBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $latitude = $settings['latitude'] ?? null;
        $longitude = $settings['longitude'] ?? null;
        $address = is_string($settings['address'] ?? null) ? trim($settings['address']) : '';
        $zoom = is_int($settings['zoom'] ?? null) ? $settings['zoom'] : 15;
        $placed = (is_int($latitude) || is_float($latitude)) && (is_int($longitude) || is_float($longitude));

        $embed = $placed ? EmbedProviders::mapFor((float) $latitude, (float) $longitude, $zoom) : null;
        $search = $address === '' ? '' : 'https://www.openstreetmap.org/search?query=' . rawurlencode($address);

        // With neither a place nor an address the block is not set up yet, and shows nothing.
        return new BlockView([
            'src' => $embed?->src ?? '',
            'link' => $embed?->link ?? $search,
            'title' => is_string($settings['title'] ?? null) ? $settings['title'] : 'Map',
            'address' => $address,
        ]);
    }
}
