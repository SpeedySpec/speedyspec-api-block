<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

/**
 * Registers the invariants every document serializer must hold, so a replacement stores documents losslessly.
 *
 * @param Closure $make
 *   Builds a fresh DocumentSerializerInterface for each test.
 *
 * @since 0.1.0
 */
function documentSerializerContract(Closure $make): void
{
    describe('DocumentSerializerInterface contract', function () use ($make) {
        $sample = function (): Document {
            $leaf = new Block('leaf', BlockName::fromString('acme/text'), 2, ['text' => 'Hi']);
            $mid = (new Block('mid', BlockName::fromString('acme/box'), 1, ['gap' => 4], [], [], [$leaf]))
                ->withExtension('acme/vis', ['hide' => true])
                ->withBinding(SettingPath::fromString('gap'), Binding::to('acme/values', ['key' => 'x']))
                ->withBinding(SettingPath::fromString('title'), Binding::to('acme/values', ['key' => 'y'])->withFallback('z'));
            $unknown = new Block('gone', BlockName::fromString('nobody/knows'), 7, ['a' => ['b' => [1, 2]]]);

            return new Document('doc-1', 'page', new BlockTree($mid, $unknown), 1);
        };

        it('round-trips a document without losing any field', function () use ($make, $sample) {
            $serializer = $make();
            $original = $sample();
            $copy = $serializer->parse($serializer->serialize($original));

            expect($copy->id)->toBe($original->id)
                ->and($copy->kind)->toBe($original->kind)
                ->and($copy->formatVersion)->toBe($original->formatVersion)
                ->and($copy->tree->ids())->toBe($original->tree->ids());

            foreach ($original->tree->walk() as $block)
            {
                $other = $copy->tree->find($block->id);

                expect($other->type->toString())->toBe($block->type->toString())
                    ->and($other->typeVersion)->toBe($block->typeVersion)
                    ->and($other->settings)->toBe($block->settings)
                    ->and($other->extensions)->toBe($block->extensions)
                    ->and(array_map(fn (Binding $b): array => $b->toArray(), $other->bindings))
                    ->toBe(array_map(fn (Binding $b): array => $b->toArray(), $block->bindings))
                    ->and(array_map(fn (Block $c): string => $c->id, $other->children))
                    ->toBe(array_map(fn (Block $c): string => $c->id, $block->children));
            }
        });

        it('rejects input that is not a document', function () use ($make) {
            expect(fn () => $make()->parse('not a document'))->toThrow(InvalidDocumentException::class);
        });
    });
}
