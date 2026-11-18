<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Example;

use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Example\Listing\ListBehaviour;
use SpeedySpec\Block\Example\Listing\ListItemOutput;
use SpeedySpec\Block\Example\Listing\ListOutput;
use SpeedySpec\Block\Example\Notice\NoticeAdminOutput;
use SpeedySpec\Block\Example\Notice\NoticeOutput;

/**
 * A small block package that shows how one is written: a notice, and a list with its items.
 *
 * It depends only on the contracts package. Each block type is defined by data, its behaviour holds logic with no
 * appearance, and its output draws the view through the escaper it is handed.
 *
 * @since 0.1.0
 */
class ExampleBlockPackage implements BlockPackageInterface
{
    public function name(): string
    {
        return 'example/blocks';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $notice = BlockName::fromString('example/notice');
        $list = BlockName::fromString('example/list');
        $item = BlockName::fromString('example/list-item');

        $registrar->type(BlockType::fromArray([
            'name' => 'example/notice',
            'title' => 'Notice',
            'description' => 'A short message with a tone.',
            'category' => 'text',
            'keywords' => ['alert', 'message'],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string', 'default' => '', 'title' => 'Text'],
                    'tone' => ['enum' => ['info', 'warning'], 'default' => 'info', 'title' => 'Tone'],
                    'internalNote' => [
                        'type' => 'string',
                        'default' => '',
                        'title' => 'Internal note',
                        'description' => 'Shown to editors only.',
                        'x-private' => true,
                    ],
                ],
            ],
            'assets' => ['example-notice'],
            'examples' => [['text' => 'Heads up', 'tone' => 'warning', 'internalNote' => 'Reviewed by legal']],
        ]));
        $registrar->output($notice, new NoticeOutput());
        $registrar->output($notice, new NoticeAdminOutput(new NoticeOutput()), Mode::Admin);

        $registrar->type(BlockType::fromArray([
            'name' => 'example/list',
            'title' => 'List',
            'description' => 'A bulleted or numbered list.',
            'category' => 'text',
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'ordered' => ['type' => 'boolean', 'default' => false, 'title' => 'Numbered'],
                    'label' => ['type' => 'string', 'default' => '', 'title' => 'Accessible label'],
                ],
            ],
            'children' => ['example/list-item'],
            'provides' => ['example/list-ordered' => 'ordered'],
            'examples' => [['label' => 'Steps']],
        ]));
        $registrar->behaviour($list, new ListBehaviour());
        $registrar->output($list, new ListOutput());

        $registrar->type(BlockType::fromArray([
            'name' => 'example/list-item',
            'title' => 'List item',
            'description' => 'One entry in a list.',
            'category' => 'text',
            'settings' => [
                'type' => 'object',
                'properties' => ['text' => ['type' => 'string', 'default' => '', 'title' => 'Text']],
            ],
            'parents' => ['example/list'],
            'uses' => ['example/list-ordered'],
            'examples' => [['text' => 'First']],
        ]));
        $registrar->output($item, new ListItemOutput());
    }
}
