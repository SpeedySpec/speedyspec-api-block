<?php

declare(strict_types=1);

use SpeedySpec\Block\Interactive\Domain\InteractiveTypesPackage;
use SpeedySpec\Block\Interactive\InteractivePackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('InteractivePackage', function () {
    blockPackageContract(
        fn () => new InteractivePackage(),
        __DIR__ . '/../composer.json',
        [fn () => new InteractiveTypesPackage(stoppedClock())],
        'outputs',
    );
});
