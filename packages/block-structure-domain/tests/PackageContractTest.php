<?php

declare(strict_types=1);

use SpeedySpec\Block\Structure\Domain\StructureTypesPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('StructureTypesPackage', function () {
    blockPackageContract(fn () => new StructureTypesPackage(), __DIR__ . '/../composer.json', [], 'definitions');
});
