<?php

namespace Habib\LaravelCrud;

use Habib\LaravelCrud\Commands\LaravelCrudCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelCrudServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-crud')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_crud_table')
            ->hasCommand(LaravelCrudCommand::class);
    }
}
