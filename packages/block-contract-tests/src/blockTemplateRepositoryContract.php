<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Registers the invariants every template repository must hold, so a replacement behaves like the default.
 *
 * @param Closure $make
 *   Builds a fresh, empty BlockTemplateRepositoryInterface for each test.
 *
 * @since 0.1.0
 */
function blockTemplateRepositoryContract(Closure $make): void
{
    $template = function (string $name, string $title = 'Title'): BlockTemplate {
        $box = BlockName::fromString('acme/box');
        $tree = new BlockTree(new Block('r1', $box, 1, [], [], [], [new Block('c1', $box)]), new Block('r2', $box));

        return new BlockTemplate($name, $title, $tree, 'A description.', 'sections');
    };

    describe('BlockTemplateRepositoryInterface contract', function () use ($make, $template) {
        it('finds nothing under an unknown name and lists nothing when empty', function () use ($make) {
            expect($make()->find('acme/nope'))->toBeNull()->and($make()->all())->toBe([]);
        });

        it('gives back a saved template with its details and blocks', function () use ($make, $template) {
            $repository = $make();
            $repository->save($template('acme/hero', 'Hero'));
            $found = $repository->find('acme/hero');

            expect($found->name)->toBe('acme/hero')
                ->and($found->title)->toBe('Hero')
                ->and($found->description)->toBe('A description.')
                ->and($found->category)->toBe('sections')
                ->and($found->tree->ids())->toBe(['r1', 'c1', 'r2']);
        });

        it('lists templates in the order they were first saved', function () use ($make, $template) {
            $repository = $make();
            $repository->save($template('acme/one', 'One'));
            $repository->save($template('acme/two', 'Two'));
            $repository->save($template('acme/one', 'First'));

            expect(array_map(fn (BlockTemplate $t) => $t->title, $repository->all()))->toBe(['First', 'Two']);
        });

        it('removes a template and leaves the others', function () use ($make, $template) {
            $repository = $make();
            $repository->save($template('acme/one'));
            $repository->save($template('acme/two'));
            $repository->remove('acme/one');

            expect($repository->find('acme/one'))->toBeNull()->and($repository->find('acme/two'))->not->toBeNull();
        });

        it('ignores the removal of an unknown name', function () use ($make) {
            expect(fn () => $make()->remove('acme/nope'))->not->toThrow(\Throwable::class);
        });
    });
}
