<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\CurrentItem;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;

test('context is immutable, filters by key, and keeps the current item', function () {
    $item = new CurrentItem(['id' => 9], 0, 'q1');
    $ctx = Context::empty()->with('acme/form-id', 'f1')->with('acme/other', 2)->withCurrentItem($item);
    $only = $ctx->only(['acme/form-id']);
    expect(Context::empty()->has('acme/form-id'))->toBeFalse()
        ->and($only->all())->toBe(['acme/form-id' => 'f1'])->and($only->currentItem())->toBe($item)
        ->and($ctx->get('missing'))->toBeNull();
});

test('context holds values of any type and has() sees a null value', function () {
    $ctx = Context::empty()->with('acme/n', null)->with('acme/list', [1, 2]);
    expect($ctx->has('acme/n'))->toBeTrue()->and($ctx->get('acme/n'))->toBeNull()
        ->and($ctx->get('acme/list'))->toBe([1, 2]);
});

test('a data source result tells no value apart from a null value', function () {
    expect(DataSourceResult::none()->hasValue())->toBeFalse()
        ->and(DataSourceResult::value(null)->hasValue())->toBeTrue()
        ->and(DataSourceResult::value(5)->get())->toBe(5);
});

test('admin context with() changes only the named field', function () {
    $admin = new AdminContext(['k' => 1], [], ['a' => ['source' => 's', 'used' => 'source']]);
    $changed = $admin->with(hiddenOnFront: true);
    expect($changed->hiddenOnFront)->toBeTrue()->and($changed->values)->toBe(['k' => 1])
        ->and($changed->bindings)->toBe($admin->bindings)->and($admin->hiddenOnFront)->toBeFalse();
});

test('block view copies return new instances and leave the original alone', function () {
    $view = new BlockView(['a' => 1]);
    $admin = new AdminContext();
    $extended = $view->withExtension('acme/anim', ['x' => 1])->withAdmin($admin);
    expect($extended)->not->toBe($view)->and($extended->extensions)->toBe(['acme/anim' => ['x' => 1]])
        ->and($extended->admin)->toBe($admin)->and($extended->values)->toBe(['a' => 1])
        ->and($view->extensions)->toBe([])->and($view->admin)->toBeNull()
        ->and($extended->withAdmin(null)->admin)->toBeNull();
});
