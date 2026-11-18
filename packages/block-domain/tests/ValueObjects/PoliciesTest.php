<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\ChildrenPolicy;
use SpeedySpec\Block\Domain\ValueObjects\ParentPolicy;

test('children and parent policies', function () {
    $form = BlockName::fromString('acme/form');
    expect(ChildrenPolicy::none()->allows($form))->toBeFalse()
        ->and(ChildrenPolicy::any()->allows($form))->toBeTrue()
        ->and(ChildrenPolicy::fromArray(['acme/form'])->allows($form))->toBeTrue()
        ->and(ParentPolicy::any()->allows(null))->toBeTrue()
        ->and(ParentPolicy::only($form)->allows(null))->toBeFalse()
        ->and(ParentPolicy::only($form)->allows($form))->toBeTrue();
});

test('an only-policy rejects names outside its list', function () {
    $form = BlockName::fromString('acme/form');
    $other = BlockName::fromString('acme/other');
    expect(ChildrenPolicy::only($form)->allows($other))->toBeFalse()
        ->and(ParentPolicy::only($form)->allows($other))->toBeFalse();
});
