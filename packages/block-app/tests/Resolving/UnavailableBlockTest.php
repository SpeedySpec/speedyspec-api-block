<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Exceptions\UnavailableBlockException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;

function unavailableRenderer(): array
{
    return renderer(function ($c) {
        types($c)->add(type('acme/image'));
        types($c)->add(type('acme/ok'));
        behaviours($c)->set(BlockName::fromString('acme/image'), new class implements BlockBehaviourInterface {
            public function view(
                Block $block,
                BlockType $type,
                array $settings,
                Context $context,
                Mode $mode,
            ): BlockView
            {
                throw new UnavailableBlockException(IssueKind::UnavailableMedia, 'The image is missing.', 'media');
            }
        });
        outputs($c)->set(BlockName::fromString('acme/image'), fixedOutput('[image]'));
        outputs($c)->set(BlockName::fromString('acme/ok'), fixedOutput('ok'));
    });
}

test('a block its behaviour calls unavailable draws nothing for a visitor and says why', function () {
    [$renderer] = unavailableRenderer();
    $result = $renderer->render(doc(block('1', 'acme/image'), block('2', 'acme/ok')), new RenderRequest(Mode::Front));

    expect($result->output)->toBe('ok')
        ->and(kinds($result->issues))->toBe([IssueKind::UnavailableMedia])
        ->and($result->issues[0]->blockId)->toBe('1')
        ->and($result->issues[0]->message)->toBe('The image is missing.')
        ->and($result->issues[0]->path)->toBe('media');
});

test('in admin mode an unavailable block is drawn as the placeholder', function () {
    [$renderer] = unavailableRenderer();
    $result = $renderer->render(doc(block('1', 'acme/image'), block('2', 'acme/ok')), new RenderRequest(Mode::Admin));

    expect($result->output)
        ->toBe('<div class="block-placeholder" data-block-id="1" data-block-type="acme/image"></div>ok');
});
