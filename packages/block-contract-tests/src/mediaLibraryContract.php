<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;

/**
 * Registers the invariants every media library must hold, so media blocks and builders work over any host's files.
 *
 * @param Closure $make
 *   Builds a fresh, empty MediaLibraryInterface for each test.
 *
 * @since 0.1.0
 */
function mediaLibraryContract(Closure $make): void
{
    describe('MediaLibraryInterface contract', function () use ($make) {
        $png = "\x89PNG\r\n\x1A\n";
        $upload = fn (string $name, string $type = 'image/png', string $bytes = "\x89PNG\r\n\x1A\nx"): MediaUpload
            => new MediaUpload($name, $type, $bytes);

        it('finds an upload by its id, with its type, kind and title', function () use ($make, $upload) {
            $library = $make();
            $item = $library->upload($upload('Sunset.png'));
            $found = $library->find($item->id);

            expect($found)->not->toBeNull()
                ->and($found->id)->toBe($item->id)
                ->and($found->mimeType)->toBe('image/png')
                ->and($found->kind)->toBe(MediaKind::Image)
                ->and($found->title)->toBe('Sunset.png')
                ->and($found->url)->not->toBe('');
        });

        it('finds nothing for an id it never gave', function () use ($make) {
            expect($make()->find('missing'))->toBeNull()->and($make()->find(''))->toBeNull();
        });

        it('searches titles regardless of case, newest first', function () use ($make, $upload) {
            $library = $make();
            $library->upload($upload('Beach day.png'));
            $library->upload($upload('notes.pdf', 'application/pdf', '%PDF-1.7'));
            $library->upload($upload('BEACH night.png'));

            $page = $library->search(new MediaQuery('beach'));

            expect(array_map(fn ($item) => $item->title, $page->items))->toBe(['BEACH night.png', 'Beach day.png'])
                ->and($page->total)->toBe(2);
        });

        it('searches by kind', function () use ($make, $upload) {
            $library = $make();
            $library->upload($upload('a.png'));
            $library->upload($upload('b.pdf', 'application/pdf', '%PDF-1.7'));

            $files = $library->search(new MediaQuery(kind: MediaKind::File));
            $videos = $library->search(new MediaQuery(kind: MediaKind::Video));

            expect(array_map(fn ($item) => $item->title, $files->items))->toBe(['b.pdf'])
                ->and($videos->items)->toBe([])
                ->and($videos->total)->toBe(0);
        });

        it('gives one page at a time and counts every match', function () use ($make, $upload) {
            $library = $make();

            foreach (['one.png', 'two.png', 'three.png'] as $name)
            {
                $library->upload($upload($name));
            }

            $first = $library->search(new MediaQuery(page: 1, perPage: 2));
            $second = $library->search(new MediaQuery(page: 2, perPage: 2));
            $beyond = $library->search(new MediaQuery(page: 5, perPage: 2));

            expect(array_map(fn ($item) => $item->title, $first->items))->toBe(['three.png', 'two.png'])
                ->and(array_map(fn ($item) => $item->title, $second->items))->toBe(['one.png'])
                ->and($first->total)->toBe(3)
                ->and($beyond->items)->toBe([])
                ->and($beyond->total)->toBe(3);
        });

        it('keeps two uploads with the same name apart', function () use ($make, $upload, $png) {
            $library = $make();
            $first = $library->upload($upload('same.png', 'image/png', $png . 'first'));
            $second = $library->upload($upload('same.png', 'image/png', $png . 'second'));

            expect($first->id)->not->toBe($second->id)
                ->and($first->url)->not->toBe($second->url)
                ->and($library->find($first->id))->not->toBeNull()
                ->and($library->search(new MediaQuery('same'))->total)->toBe(2);
        });
    });
}
