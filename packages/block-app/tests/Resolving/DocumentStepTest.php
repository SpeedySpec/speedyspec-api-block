<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\DocumentStepInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

if (!function_exists('documentStep'))
{
    /**
     * Builds a document step from a callable.
     */
    function documentStep(callable $step): DocumentStepInterface
    {
        return new class($step) implements DocumentStepInterface {
            public function __construct(private readonly mixed $step)
            {
            }

            public function context(Document $document, Context $context, Mode $mode): Context
            {
                return ($this->step)($document, $context, $mode);
            }
        };
    }
}

test('a document step sees the whole document once and gives the root blocks context', function () {
    $calls = 0;
    [$resolver] = resolver(function ($c) use (&$calls) {
        types($c)->add(type('acme/box', ['children' => true]));
        $c->get(StepRegistryInterface::class)->add(DocumentStepInterface::class, documentStep(
            function (Document $document, Context $context) use (&$calls) {
                $calls++;

                return $context->with('acme/count', count($document->tree->ids()));
            },
        ));
    });

    $resolved = $resolver->resolve(doc(block('a', 'acme/box', [], block('child', 'acme/box')), block('b')), Mode::Front);

    expect($resolved->find('a')->context->get('acme/count'))->toBe(3)
        ->and($resolved->find('child')->context->get('acme/count'))->toBe(3)
        ->and($calls)->toBe(1);
});

test('document steps run in priority order, each given what the last returned', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        $steps = $c->get(StepRegistryInterface::class);
        $append = fn (string $letter) => documentStep(
            fn ($d, Context $c) => $c->with('acme/trail', $c->get('acme/trail') . $letter),
        );
        $steps->add(DocumentStepInterface::class, $append('b'), 20);
        $steps->add(DocumentStepInterface::class, $append('a'), 10);
    });

    expect($resolver->resolve(doc(block('a')), Mode::Front)->find('a')->context->get('acme/trail'))->toBe('ab');
});

test('a failing document step is recorded and the document still resolves', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        $c->get(StepRegistryInterface::class)->add(DocumentStepInterface::class, documentStep(
            fn () => throw new RuntimeException('no'),
        ));
    });

    $resolved = $resolver->resolve(doc(block('a')), Mode::Front);

    expect(idsAndKinds($resolved->issues))->toBe([['', IssueKind::StepFailed]])
        ->and($resolved->find('a')->isResolved())->toBeTrue();
});
