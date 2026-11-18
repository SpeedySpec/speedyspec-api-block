<?php

declare(strict_types=1);

use SpeedySpec\Block\Text\Domain\TextTypesPackage;
use SpeedySpec\Block\Text\TextPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('TextPackage', function () {
    blockPackageContract(
        fn () => TextPackage::from(textHost()),
        __DIR__ . '/../composer.json',
        [fn () => new TextTypesPackage()],
        'outputs',
    );
});
