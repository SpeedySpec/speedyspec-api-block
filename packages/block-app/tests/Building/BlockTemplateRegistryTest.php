<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Building\BlockTemplateRegistry;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

test('templates are listed in registration order and replaced by name', function () {
    $registry = new BlockTemplateRegistry();
    $registry->add(new BlockTemplate('acme/one', 'One', new BlockTree(block('a'))));
    $registry->add(new BlockTemplate('acme/two', 'Two', new BlockTree(block('a'))));
    $registry->add(new BlockTemplate('acme/one', 'First', new BlockTree(block('a'))));

    expect(array_map(fn (BlockTemplate $t) => $t->title, $registry->all()))->toBe(['First', 'Two'])
        ->and($registry->find('acme/two')->title)->toBe('Two')
        ->and($registry->find('acme/nope'))->toBeNull();

    $registry->remove('acme/one');
    $registry->remove('acme/never-there');

    expect($registry->find('acme/one'))->toBeNull()->and(count($registry->all()))->toBe(1);
});
