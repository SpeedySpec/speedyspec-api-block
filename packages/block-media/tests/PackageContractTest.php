<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Media\Domain\MediaTypesPackage;
use SpeedySpec\Block\Media\MediaPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('MediaPackage', function () {
    blockPackageContract(
        fn () => MediaPackage::from(mediaHost()),
        __DIR__ . '/../composer.json',
        [fn () => new MediaTypesPackage(mediaHost()->get(MediaLibraryInterface::class))],
        'outputs',
    );
});
