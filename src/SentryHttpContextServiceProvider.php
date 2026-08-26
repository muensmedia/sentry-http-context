<?php

namespace Muensmedia\SentryHttpContext;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SentryHttpContextServiceProvider extends PackageServiceProvider
{

    public function configurePackage(Package $package): void
    {
        $package
            ->name('sentry-http-context');
    }
}