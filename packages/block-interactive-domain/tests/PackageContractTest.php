<?php

declare(strict_types=1);

use SpeedySpec\Block\Interactive\Domain\InteractiveTypesPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('InteractiveTypesPackage', function () {
    blockPackageContract(
        fn () => new InteractiveTypesPackage(fixedClock()),
        __DIR__ . '/../composer.json',
        [],
        'definitions',
    );
});
