<?php

declare(strict_types=1);

use SpeedySpec\Block\Site\Domain\SiteTypesPackage;
use SpeedySpec\Block\Site\SitePackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('SitePackage', function () {
    blockPackageContract(
        fn () => new SitePackage(),
        __DIR__ . '/../composer.json',
        [fn () => SiteTypesPackage::from(siteHost())],
        'outputs',
    );
});
