<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\RenderResult;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;

test('a render request defaults to front html', function () {
    $request = new RenderRequest();

    expect($request->mode)->toBe(Mode::Front)->and($request->target)->toBe('html');
});

test('a render request carries the mode and target it was given', function () {
    $request = new RenderRequest(Mode::Admin, 'text');

    expect($request->mode)->toBe(Mode::Admin)->and($request->target)->toBe('text');
});

test('render input joins child passes in order', function () {
    expect(renderInput(childPasses: ['<li>a</li>', '<li>b</li>'])->children())->toBe('<li>a</li><li>b</li>')
        ->and(renderInput(childPasses: [])->children())->toBe('');
});

test('render input has no admin context unless one is given', function () {
    expect(renderInput()->admin)->toBeNull();
});

test('a render result reports whether it has issues', function () {
    $resolved = new ResolvedDocument(new Document('d', 'page', new BlockTree()), Mode::Front, [], []);
    $clean = new RenderResult('<p></p>', $resolved, [], ['a']);
    $flawed = new RenderResult('', $resolved, [new Issue('a', IssueKind::MissingOutput, 'none')], []);

    expect($clean->hasIssues())->toBeFalse()
        ->and($clean->output)->toBe('<p></p>')
        ->and($clean->assets)->toBe(['a'])
        ->and($flawed->hasIssues())->toBeTrue();
});

test('the render stage has its own issue kinds', function () {
    expect(IssueKind::MissingOutput->value)->toBe('missing-output')
        ->and(IssueKind::OutputFailed->value)->toBe('output-failed')
        ->and(IssueKind::InvalidAttribute->value)->toBe('invalid-attribute');
});
