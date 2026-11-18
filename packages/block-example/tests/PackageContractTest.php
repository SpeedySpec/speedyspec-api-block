<?php

declare(strict_types=1);

use SpeedySpec\Block\Example\ExampleBlockPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('ExampleBlockPackage', function () {
    blockPackageContract(fn () => new ExampleBlockPackage(), __DIR__ . '/../composer.json');
});
