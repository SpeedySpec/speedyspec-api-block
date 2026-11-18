<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Presentation\BreakpointRegistry;
use SpeedySpec\Block\App\Presentation\DesignTokenRegistry;
use SpeedySpec\Block\App\Presentation\StyleCollector;
use SpeedySpec\Block\Structure\Domain\StructureTypesPackage;
use SpeedySpec\Block\Structure\StructurePackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('StructurePackage', function () {
    blockPackageContract(
        fn () => new StructurePackage(
            new StyleCollector(new BreakpointRegistry(), new DesignTokenRegistry()),
            new BreakpointRegistry(),
        ),
        __DIR__ . '/../composer.json',
        [fn () => new StructureTypesPackage()],
        'outputs',
    );
});
