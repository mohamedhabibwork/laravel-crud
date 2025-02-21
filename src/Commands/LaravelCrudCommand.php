<?php

namespace Habib\LaravelCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LaravelCrudCommand extends Command
{
    public $signature = 'laravel-crud:publish';

    public $description = 'Publish Laravel CRUD components to your application';

    public function handle(): int
    {
        $this->publishDataTable();
        $this->publishRepository();
        $this->publishTraits();
        $this->publishStubs();

        $this->info('All components have been published successfully!');

        return self::SUCCESS;
    }

    protected function publishDataTable(): void
    {
        $source = __DIR__.'/../DataTables/BaseDataTable.php';
        $destination = app_path('DataTables/BaseDataTable.php');

        $this->publishFile($source, $destination, 'Habib\\LaravelCrud\\DataTables', 'App\\DataTables');
        $this->info('BaseDataTable published successfully.');
    }

    protected function publishRepository(): void
    {
        $repositorySource = __DIR__.'/../Repository/BaseRepository.php';
        $interfaceSource = __DIR__.'/../Repository/BaseRepositoryInterface.php';

        $repositoryDest = app_path('Http/Repository/BaseRepository.php');
        $interfaceDest = app_path('Http/Repository/BaseRepositoryInterface.php');

        $this->publishFile($repositorySource, $repositoryDest, 'Habib\\LaravelCrud\\Repository', 'App\\Http\\Repository');
        $this->publishFile($interfaceSource, $interfaceDest, 'Habib\\LaravelCrud\\Repository', 'App\\Http\\Repository');
        $this->info('Repository files published successfully.');
    }

    protected function publishTraits(): void
    {
        $traitsPath = __DIR__.'/../Traits';
        $destinationPath = app_path('Http/Controllers/Traits');

        File::ensureDirectoryExists($destinationPath);

        $traits = File::files($traitsPath);
        foreach ($traits as $trait) {
            $destination = $destinationPath.'/'.$trait->getFilename();
            $this->publishFile($trait->getPathname(), $destination, 'Habib\\LaravelCrud\\Traits', 'App\\Http\\Controllers\\Traits');
        }
        $this->info('Traits published successfully.');
    }

    protected function publishStubs(): void
    {
        $stubsPath = __DIR__.'/../../stubs';
        $destinationPath = base_path('stubs');

        File::ensureDirectoryExists($destinationPath);

        $stubs = File::files($stubsPath);
        foreach ($stubs as $stub) {
            $destination = $destinationPath.'/'.$stub->getFilename();
            File::copy($stub->getPathname(), $destination);
        }
        $this->info('Stubs published successfully.');
    }

    protected function publishFile(string $source, string $destination, string $oldNamespace, string $newNamespace): void
    {
        if (! File::exists($source)) {
            $this->error("Source file {$source} does not exist.");

            return;
        }

        File::ensureDirectoryExists(dirname($destination));

        $content = File::get($source);
        $content = str_replace($oldNamespace, $newNamespace, $content);

        File::put($destination, $content);
    }
}
