<?php

declare(strict_types=1);

use SpeedySpec\Block\Media\Domain\MediaTypesPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('MediaTypesPackage', function () {
    blockPackageContract(fn () => new MediaTypesPackage(library()), __DIR__ . '/../composer.json', [], 'definitions');
});
