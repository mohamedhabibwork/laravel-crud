<?php

namespace Habib\LaravelCrud\Tests\Commands;

use Habib\LaravelCrud\Tests\TestCase;
use Illuminate\Support\Facades\File;

class LaravelCrudCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean up any previously published files
        $this->cleanupPublishedFiles();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        $this->cleanupPublishedFiles();
        parent::tearDown();
    }

    /** @test */
    public function it_can_publish_datatable_files()
    {
        $this->artisan('laravel-crud:publish')
            ->assertSuccessful();

        $this->assertFileExists(app_path('DataTables/BaseDataTable.php'));
        $this->assertStringContainsString(
            'namespace App\\DataTables;',
            File::get(app_path('DataTables/BaseDataTable.php'))
        );
    }

    /** @test */
    public function it_can_publish_repository_files()
    {
        $this->artisan('laravel-crud:publish')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Http/Repository/BaseRepository.php'));
        $this->assertFileExists(app_path('Http/Repository/BaseRepositoryInterface.php'));

        $this->assertStringContainsString(
            'namespace App\\Http\\Repository;',
            File::get(app_path('Http/Repository/BaseRepository.php'))
        );

        $this->assertStringContainsString(
            'namespace App\\Http\\Repository;',
            File::get(app_path('Http/Repository/BaseRepositoryInterface.php'))
        );
    }

    /** @test */
    public function it_can_publish_traits()
    {
        $this->artisan('laravel-crud:publish')
            ->assertSuccessful();

        $traitsPath = app_path('Http/Controllers/Traits');
        $this->assertDirectoryExists($traitsPath);

        // Test for some of the known traits
        $this->assertFileExists($traitsPath.'/CreateTrait.php');
        $this->assertFileExists($traitsPath.'/UpdateTrait.php');
        $this->assertFileExists($traitsPath.'/DestroyTrait.php');

        // Verify namespace in one of the traits
        $this->assertStringContainsString(
            'namespace App\\Http\\Controllers\\Traits;',
            File::get($traitsPath.'/CreateTrait.php')
        );
    }

    /** @test */
    public function it_can_publish_stubs()
    {
        $this->artisan('laravel-crud:publish')
            ->assertSuccessful();

        $stubsPath = base_path('stubs');
        $this->assertDirectoryExists($stubsPath);

        // Test for some of the known stubs
        $this->assertFileExists($stubsPath.'/controller.crud.stub');
        $this->assertFileExists($stubsPath.'/repository.stub');
        $this->assertFileExists($stubsPath.'/repository.interface.stub');
    }

    protected function cleanupPublishedFiles(): void
    {
        // Clean up DataTables
        File::deleteDirectory(app_path('DataTables'));

        // Clean up Repository
        File::deleteDirectory(app_path('Http/Repository'));

        // Clean up Traits
        File::deleteDirectory(app_path('Http/Controllers/Traits'));

        // Clean up Stubs
        File::deleteDirectory(base_path('stubs'));
    }
}
