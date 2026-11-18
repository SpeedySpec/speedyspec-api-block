<?php

declare(strict_types=1);

use SpeedySpec\Block\Site\Domain\SiteTypesPackage;

use function SpeedySpec\Block\ContractTests\blockPackageContract;

describe('SiteTypesPackage with a site map', function () {
    blockPackageContract(fn () => new SiteTypesPackage(siteMap()), __DIR__ . '/../composer.json', [], 'definitions');
});

describe('SiteTypesPackage without a site map', function () {
    blockPackageContract(fn () => new SiteTypesPackage(), __DIR__ . '/../composer.json', [], 'definitions');
});
