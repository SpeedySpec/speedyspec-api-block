<?php

declare(strict_types=1);

use SpeedySpec\Block\Actions\Domain\ActionsTypesPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('ActionsTypesPackage with a media library', function () {
    blockPackageContract(
        fn () => new ActionsTypesPackage(actionsLibrary()),
        __DIR__ . '/../composer.json',
        [],
        'definitions',
    );
});

describe('ActionsTypesPackage without a media library', function () {
    blockPackageContract(fn () => new ActionsTypesPackage(), __DIR__ . '/../composer.json', [], 'definitions');
});
