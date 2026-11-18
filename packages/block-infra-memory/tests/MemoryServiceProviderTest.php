<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Infra\Memory\InMemoryDocumentRepository;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;

test('binds an in-memory document repository by default', function () {
    $c = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($c);

    expect($c->get(DocumentRepositoryInterface::class))->toBeInstanceOf(InMemoryDocumentRepository::class);
});

test('binds the document repository as a replaceable contract', function () {
    $c = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($c);
    $mine = new InMemoryDocumentRepository();
    $c->add(DocumentRepositoryInterface::class, fn () => $mine);

    expect($c->get(DocumentRepositoryInterface::class))->toBe($mine);
});
