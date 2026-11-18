<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;

$setup = function ($c) {
    types($c)->add(type('acme/form', [
        'children' => ['acme/field'],
        'provides' => ['acme/form-id' => 'formId'],
        'settings' => ['type' => 'object', 'properties' => ['formId' => ['type' => 'string', 'default' => 'f1']]],
    ]));
    types($c)->add(type('acme/field', [
        'parents' => ['acme/form'],
        'uses' => ['acme/form-id'],
        'assets' => ['field'],
        'settings' => ['type' => 'object', 'required' => ['key'], 'properties' => ['key' => ['type' => 'string']]],
    ]));
    outputs($c)->set(BlockName::fromString('acme/form'), tag('form'));
    outputs($c)->set(BlockName::fromString('acme/field'), new class implements BlockOutputInterface {
        public function render(RenderInput $input): string
        {
            return sprintf(
                '<input name="%s" form="%s">',
                $input->escaper->attribute((string) $input->view->values['key']),
                $input->escaper->attribute((string) $input->context->get('acme/form-id')),
            );
        }
    });
    outputs($c)->set(BlockName::fromString('acme/field'), fixedOutput('[editing field]'), Mode::Admin);
};
$document = fn () => doc(block(
    'f',
    'acme/form',
    [],
    block('n', 'acme/field', ['key' => 'name']),
    block('bad', 'acme/field', []),
));

test('previews one block with the context its ancestors provide', function () use ($setup, $document) {
    [$previewer] = previewer($setup);

    $preview = $previewer->preview($document(), 'n', new RenderRequest(Mode::Front));

    expect($preview->output)->toBe('<input name="name" form="f1">')
        ->and($preview->issues)->toBe([])
        ->and($preview->assets)->toBe(['field'])
        ->and($preview->block->view->values)->toBe(['key' => 'name'])
        ->and($preview->blockId)->toBe('n');
});

test('admin preview uses the admin output and carries the admin context', function () use ($setup, $document) {
    [$previewer] = previewer($setup);

    $preview = $previewer->preview($document(), 'n', new RenderRequest(Mode::Admin));

    expect($preview->output)->toBe('[editing field]')
        ->and($preview->block->admin)->toBeInstanceOf(AdminContext::class);
});

test('a broken block previews as a placeholder in admin mode and as nothing for a visitor', function () use (
    $setup,
    $document,
) {
    [$previewer] = previewer($setup);

    $admin = $previewer->preview($document(), 'bad', new RenderRequest(Mode::Admin));
    $front = $previewer->preview($document(), 'bad', new RenderRequest(Mode::Front));

    expect($admin->output)
        ->toBe('<div class="block-placeholder" data-block-id="bad" data-block-type="acme/field"></div>')
        ->and(kinds($admin->issues))->toBe([IssueKind::InvalidSettings])
        ->and($admin->block->admin->issues)->toHaveCount(1)
        ->and($front->output)->toBe('')
        ->and(kinds($front->issues))->toBe([IssueKind::InvalidSettings]);
});

test('previewing a parent draws its children and reports only that subtree', function () use ($setup) {
    [$previewer] = previewer($setup);
    $document = doc(
        block('x', 'gone/away'),
        block('f', 'acme/form', [], block('n', 'acme/field', ['key' => 'name'])),
    );

    $preview = $previewer->preview($document, 'f', new RenderRequest(Mode::Front));

    expect($preview->output)->toBe('<form><input name="name" form="f1"></form>')
        ->and($preview->issues)->toBe([]);
});

test('a missing id gives no preview, and an unreached block gives one without a resolved block', function () use (
    $setup,
) {
    [$previewer] = previewer($setup);
    $document = doc(block('f', 'acme/form', ['formId' => 5], block('n', 'acme/field', ['key' => 'name'])));

    $unreached = $previewer->preview($document, 'n', new RenderRequest(Mode::Admin));

    expect($previewer->preview($document, 'nope', new RenderRequest()))->toBeNull()
        ->and($unreached->block)->toBeNull()
        ->and($unreached->output)->toBe('')
        ->and($unreached->issues)->toBe([]);
});

test('a misplaced block previews with the reason it was left out', function () use ($setup) {
    [$previewer] = previewer(function ($c) use ($setup) {
        $setup($c);
        types($c)->add(type('acme/box', ['children' => true]));
    });
    $document = doc(block('b', 'acme/box', [], block('n', 'acme/field', ['key' => 'name'])));

    $preview = $previewer->preview($document, 'n', new RenderRequest(Mode::Admin));

    expect($preview->block)->toBeNull()
        ->and($preview->output)->toBe('')
        ->and(idsAndKinds($preview->issues))->toBe([['n', IssueKind::DisallowedChild]]);
});

