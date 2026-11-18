<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\Exceptions\InvalidCollectionException;

/**
 * Registers the invariants every record store must hold, so repositories behave the same over any storage.
 *
 * @param Closure $make
 *   Builds a fresh, empty RecordStoreInterface for each test.
 *
 * @since 0.1.0
 */
function recordStoreContract(Closure $make): void
{
    describe('RecordStoreInterface contract', function () use ($make) {
        it('has nothing under an unknown key and lists nothing when empty', function () use ($make) {
            $store = $make();

            expect($store->get('documents', 'nope'))->toBeNull()
                ->and($store->keys('documents'))->toBe([]);
        });

        it('gives back exactly what was put', function () use ($make) {
            $store = $make();
            $value = "{\"text\":\"línea\\n\u{1F600}\"}\n  trailing space ";
            $store->put('documents', 'a', $value);

            expect($store->get('documents', 'a'))->toBe($value);
        });

        it('keeps a large value whole', function () use ($make) {
            $store = $make();
            $value = str_repeat('0123456789abcdef', 20000);
            $store->put('documents', 'big', $value);

            expect($store->get('documents', 'big'))->toBe($value);
        });

        it('replaces a value put again under the same key without moving it in the list', function () use ($make) {
            $store = $make();
            $store->put('templates', 'a', 'one');
            $store->put('templates', 'b', 'two');
            $store->put('templates', 'a', 'three');

            expect($store->get('templates', 'a'))->toBe('three')
                ->and($store->keys('templates'))->toBe(['a', 'b']);
        });

        it('lists keys in the order they were first put', function () use ($make) {
            $store = $make();

            foreach (['m', 'z', 'a', '10', '9'] as $key)
            {
                $store->put('templates', $key, $key);
            }

            expect($store->keys('templates'))->toBe(['m', 'z', 'a', '10', '9']);
        });

        it('deletes a record, and deleting an unknown key is not an error', function () use ($make) {
            $store = $make();
            $store->put('documents', 'a', 'one');
            $store->put('documents', 'b', 'two');
            $store->delete('documents', 'a');
            $store->delete('documents', 'never');

            expect($store->get('documents', 'a'))->toBeNull()
                ->and($store->keys('documents'))->toBe(['b']);
        });

        it('puts a deleted key back at the end of the list', function () use ($make) {
            $store = $make();
            $store->put('documents', 'a', 'one');
            $store->put('documents', 'b', 'two');
            $store->delete('documents', 'a');
            $store->put('documents', 'a', 'again');

            expect($store->keys('documents'))->toBe(['b', 'a']);
        });

        it('keeps collections apart', function () use ($make) {
            $store = $make();
            $store->put('documents', 'a', 'document');
            $store->put('templates', 'a', 'template');
            $store->delete('templates', 'a');

            expect($store->get('documents', 'a'))->toBe('document')
                ->and($store->keys('templates'))->toBe([]);
        });

        it('treats any string as a key, exactly', function (string $key) use ($make) {
            $store = $make();
            $store->put('documents', $key, 'value of ' . $key);
            $store->put('documents', $key . 'x', 'other');

            expect($store->get('documents', $key))->toBe('value of ' . $key)
                ->and($store->keys('documents'))->toBe([$key, $key . 'x']);
        })->with([
            'empty' => [''],
            'path traversal' => ['../../etc/passwd'],
            'slashes' => ['acme/hero'],
            'dots' => ['..'],
            'spaces and quotes' => [" a 'b' \"c\" "],
            'unicode' => ["h\u{00E9}ro-\u{1F600}"],
            'long' => [str_repeat('k', 600)],
            'percent and wildcard' => ['100%_done'],
        ]);

        it('tells keys that differ only by case apart', function () use ($make) {
            $store = $make();
            $store->put('documents', 'Hero', 'upper');
            $store->put('documents', 'hero', 'lower');

            expect($store->get('documents', 'Hero'))->toBe('upper')
                ->and($store->get('documents', 'hero'))->toBe('lower')
                ->and($store->keys('documents'))->toBe(['Hero', 'hero']);
        });

        it('refuses a malformed collection name', function (string $collection) use ($make) {
            $store = $make();

            expect(fn () => $store->put($collection, 'a', 'x'))->toThrow(InvalidCollectionException::class)
                ->and(fn () => $store->get($collection, 'a'))->toThrow(InvalidCollectionException::class)
                ->and(fn () => $store->delete($collection, 'a'))->toThrow(InvalidCollectionException::class)
                ->and(fn () => $store->keys($collection))->toThrow(InvalidCollectionException::class);
        })->with(['', '../up', 'Documents', 'a b', 'a/b', "docs\n", str_repeat('a', 65)]);
    });
}
