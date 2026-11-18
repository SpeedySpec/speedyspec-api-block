<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Interactive\Domain\ElementIds;

function countdown(string $target, string $zone = 'Europe/London'): ?array
{
    return viewOf(block('c', 'core/countdown', ['target' => $target, 'timeZone' => $zone]));
}

test('a countdown names its moment in UTC and in words', function () {
    expect(countdown('2026-12-31T23:59'))->toMatchArray([
        'target' => '2026-12-31T23:59:00Z',
        'ended' => false,
        'when' => '31 December 2026, 23:59 (Europe/London)',
        'timeZone' => 'Europe/London',
        'label' => 'Starts in',
        'after' => 'It has started.',
    ]);
});

test('a countdown follows the zone\'s summer time, and one already past has ended', function () {
    expect(countdown('2026-07-01T09:00'))->toMatchArray(['target' => '2026-07-01T08:00:00Z', 'ended' => true]);
});

test('a local time the clocks skip moves to the next valid moment, and one they repeat takes the earlier', function () {
    expect(countdown('2027-03-28T01:30')['target'])->toBe('2027-03-28T01:30:00Z')
        ->and(countdown('2027-10-31T01:30')['target'])->toBe('2027-10-31T00:30:00Z');
});

test('a countdown in a zone nobody knows is unavailable', function () {
    $resolved = resolve(block('c', 'core/countdown', ['target' => '2026-12-31T23:59', 'timeZone' => 'Mars/Olympus']));

    expect($resolved->find('c')?->view)->toBeNull()
        ->and(issuesOf('c', $resolved))->toBe([[IssueKind::InvalidSettings, 'Unknown time zone.', 'timeZone']]);
});

test('a countdown with no date yet is not set up', function () {
    $resolved = resolve(block('c', 'core/countdown'));

    expect($resolved->find('c')?->view?->values['target'])->toBe('')
        ->and(issuesOf('c', $resolved))->toBe([]);
});

test('progress is kept within its maximum and given as a whole percentage', function () {
    expect(viewOf(block('p', 'core/progress', ['value' => 150, 'max' => 100])))
        ->toMatchArray(['value' => 100, 'max' => 100, 'percent' => 100])
        ->and(viewOf(block('p', 'core/progress', ['value' => 1, 'max' => 3]))['percent'])->toBe(33);
});

test('a counter writes its final value with its decimals, prefix and suffix', function () {
    $view = viewOf(block('n', 'core/counter', ['to' => 1234.5, 'decimals' => 2, 'prefix' => '$', 'suffix' => '+']));

    expect($view['text'])->toBe('$1,234.50+')
        ->and($view)->toMatchArray(['from' => 0, 'to' => 1234.5, 'duration' => 2000, 'decimals' => 2]);
});

test('element ids come from block ids, made safe', function () {
    expect(ElementIds::for('a b/c', 'x'))->toBe('b-a-b-c-x')
        ->and(ElementIds::for('tabs1', 'panel'))->toBe('b-tabs1-panel')
        ->and(viewOf(block('t1', 'core/tabs'))['id'])->toBe('b-t1-x')
        ->and(viewOf(block('m"1', 'core/modal'))['id'])->toBe('b-m-1-x');
});

test('an accordion\'s items share one group only when one may be open at a time', function () {
    $single = resolve(block('a', 'core/accordion', ['single' => true], block('i', 'core/accordion-item')));
    $many = resolve(block('a', 'core/accordion', [], block('i', 'core/accordion-item', ['open' => true])));

    expect($single->find('i')?->view?->values)->toBe(['title' => 'Question', 'open' => false, 'group' => 'b-a-group'])
        ->and($many->find('i')?->view?->values)->toBe(['title' => 'Question', 'open' => true, 'group' => '']);
});
