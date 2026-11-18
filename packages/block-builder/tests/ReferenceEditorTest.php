<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Builder\ReferenceEditor;
use SpeedySpec\Block\Builder\ReferenceEditorServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\EditorInterface;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;
use SpeedySpec\Block\Domain\ValueObjects\EditorRequest;

$container = function (): BlockServiceContainer {
    $c = new BlockServiceContainer();
    (new AppServiceProvider())->register($c);
    (new ReferenceEditorServiceProvider())->register($c);

    return $c;
};

test('the provider binds the reference editor and registers its script', function () use ($container) {
    $c = $container();
    $asset = $c->get(AssetRegistryInterface::class)->get(ReferenceEditor::ASSET);

    expect($c->get(EditorInterface::class))->toBeInstanceOf(ReferenceEditor::class)
        ->and($asset->source)->toBe('file')
        ->and([$asset->package, $asset->path, $asset->kind])
        ->toBe(['speedyspec/block-builder', 'src/editor.js', AssetKind::Module])
        ->and(is_file(__DIR__ . '/../' . $asset->path))->toBeTrue();
});

test('the editor names its script as an asset and escapes everything it places', function () use ($container) {
    $editor = $container()->get(EditorInterface::class);

    $page = $editor->open(new EditorRequest('a"b<c>', '/api?x="1"', ['X-CSRF-Token' => 't"k']));

    expect($editor->name())->toBe('speedyspec/block-builder')
        ->and($editor->title())->not->toBe('')
        ->and($page->assets)->toBe([ReferenceEditor::ASSET])
        ->and($page->markup)->toStartWith('<block-builder ')
        ->toContain('document="a&quot;b&lt;c&gt;"')
        ->toContain('api="/api?x=&quot;1&quot;"')
        ->toContain('headers="{&quot;X-CSRF-Token&quot;:&quot;t\\&quot;k&quot;}"')
        ->not->toContain('a"b');
});

test('a site that leaves the editor out has none bound', function () {
    $c = new BlockServiceContainer();
    (new AppServiceProvider())->register($c);

    expect($c->has(EditorInterface::class))->toBeFalse();
});

test('another editor can replace the reference editor', function () use ($container) {
    $c = new BlockServiceContainer();
    (new AppServiceProvider())->register($c);
    (new ReferenceEditorServiceProvider())->register($c);
    $c->add(EditorInterface::class, fn () => new class implements EditorInterface {
        public function name(): string
        {
            return 'other/editor';
        }

        public function title(): string
        {
            return 'Other';
        }

        public function open(EditorRequest $request): \SpeedySpec\Block\Domain\ValueObjects\EditorPage
        {
            return new \SpeedySpec\Block\Domain\ValueObjects\EditorPage([], '<other-editor></other-editor>');
        }
    });

    expect($c->get(EditorInterface::class)->name())->toBe('other/editor');
});
