<?php

declare(strict_types=1);

use SpeedySpec\Block\Actions\ActionsPackage;
use SpeedySpec\Block\Actions\Domain\ActionsTypesPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('ActionsPackage', function () {
    blockPackageContract(
        fn () => ActionsPackage::from(actionsHost()),
        __DIR__ . '/../composer.json',
        [fn () => ActionsTypesPackage::from(actionsHost())],
        'outputs',
    );
});
