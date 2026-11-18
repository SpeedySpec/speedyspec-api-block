<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\BlockBehaviourRegistry;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

function behaviour(): BlockBehaviourInterface
{
    return new class implements BlockBehaviourInterface {
        public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
        {
            return new BlockView($settings);
        }
    };
}

test('a behaviour can be set, read and replaced', function () {
    $registry = new BlockBehaviourRegistry();
    $name = BlockName::fromString('acme/box');
    $first = behaviour();
    $second = behaviour();
    expect($registry->get($name))->toBeNull();
    $registry->set($name, $first);
    expect($registry->get($name))->toBe($first);
    $registry->set($name, $second);
    expect($registry->get($name))->toBe($second)
        ->and($registry->get(BlockName::fromString('acme/other')))->toBeNull();
});
