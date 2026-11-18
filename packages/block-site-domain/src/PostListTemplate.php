<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site\Domain;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * The post list: not a block type but an arrangement of ordinary blocks, so every part of it stays editable.
 *
 * It repeats over the `core/recent-posts` collection a host registers, and binds each post's title, excerpt and
 * address into a heading, a paragraph and a button. The blocks are named, not depended on: a host without them can
 * still load this package.
 *
 * @since 0.1.0
 */
class PostListTemplate
{
    public const string NAME = 'core/post-list';

    public const string COLLECTION = 'core/recent-posts';

    /**
     * @since 0.1.0
     */
    public static function template(): BlockTemplate
    {
        $field = static fn (string $name, string $fallback): Binding => Binding::to(
            'core/current-item',
            ['field' => $name],
        )->withFallback($fallback);
        $block = static fn (string $id, string $type, array $settings, array $bindings, Block ...$children): Block
            => new Block($id, BlockName::fromString($type), 1, $settings, [], $bindings, array_values($children));

        return new BlockTemplate(
            self::NAME,
            'Post list',
            new BlockTree($block(
                'post-list',
                'core/repeat',
                ['source' => self::COLLECTION, 'limit' => 5],
                [],
                $block(
                    'post-list-item',
                    'core/section',
                    [],
                    [],
                    $block(
                        'post-list-title',
                        'core/heading',
                        ['level' => 3],
                        ['text' => $field('title', 'Post title')],
                    ),
                    $block('post-list-excerpt', 'core/paragraph', [], ['text' => $field('excerpt', '')]),
                    $block('post-list-link', 'core/button', ['text' => 'Read more'], ['link' => $field('url', '')]),
                ),
            )),
            'Recent posts, each with its title, a few words and a link.',
            'site',
        );
    }
}
