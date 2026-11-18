<?php

declare(strict_types=1);

use SpeedySpec\Block\Text\Domain\TextTypesPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('TextTypesPackage', function () {
    blockPackageContract(fn () => new TextTypesPackage(), __DIR__ . '/../composer.json', [], 'definitions');
});
