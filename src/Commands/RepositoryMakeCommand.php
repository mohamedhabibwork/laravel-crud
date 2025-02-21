<?php

namespace Habib\LaravelCrud\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:repository')]
class RepositoryMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:repository';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a repository class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Repository';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        $name = $this->input->getArgument('name');

        return str_contains($name, 'Interface')
            ? $this->resolveStubPath('/stubs/repository.interface.stub')
            : $this->resolveStubPath('/stubs/repository.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Http\Repository';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the repository already exists'],
            ['interface', null, InputOption::VALUE_NONE, 'Generate an interface repository class'],
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Generate a repository for the given model'],
        ];
    }

    public function handle()
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return false;
        }

        if ($this->option('interface')) {
            $repositoryClassName = $this->qualifyClass($this->getNameInput());
            $this->input->setOption('interface', true);
            $this->type = 'RepositoryInterface';
            $this->input->setArgument('name', $this->input->getArgument('name').'Interface');
            $status = parent::handle();
            //            if ($status === false) {
            //                return false;
            //            }

            // add to the repository service provider

            $repositoryServiceProvider = file_get_contents(app_path('Providers/RepositoryServiceProvider.php'));
            $repositoryInterfaceClassName = $this->qualifyClass($this->getNameInput());
            // check if the repository service provider already contains the repository
            if (str_contains($repositoryServiceProvider, $repositoryInterfaceClassName)) {
                return $status;
            }
            // if the array is empty, add the first element
            if (str_contains($repositoryServiceProvider, 'protected array $repositories = []')) {
                $repositoryServiceProvider = str_replace(
                    'protected array $repositories = []',
                    "protected array \$repositories = [\n        \\$repositoryInterfaceClassName::class => \\$repositoryClassName::class,",
                    $repositoryServiceProvider
                );
            }
            // if the array is not empty, add the element to the end of the array
            else {
                $repositoryServiceProvider = str_replace(
                    'protected array $repositories = [',
                    "protected array \$repositories = [\n        \\$repositoryInterfaceClassName::class => \\$repositoryClassName::class,",
                    $repositoryServiceProvider
                );
            }

            file_put_contents(
                app_path('Providers/RepositoryServiceProvider.php'),
                $repositoryServiceProvider
            );

            return $status;
        }
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());
        $content = $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
        if ($this->hasOption('model')) {
            $modelName = class_basename($this->option('model'));
            $modelClass = $this->qualifyModel($modelName);
            $columns = [];
            if (class_exists($modelClass)) {
                $model = app($modelClass);
                $columns = collect(Arr::except(Schema::getColumnListing($model->getTable()), $model->getHidden()))->map(fn ($column) => "'$column' => \$model->{$column},")->implode("\n        ");
            }
            $searches = [
                ['DummyModel' => $modelClass, '{{ model }}' => $modelName, '{{model}}' => strtolower($modelName)],
                ['DummySingularModel' => str($modelName)->singular(), '{{ singularModel }}' => str($modelName)->singular()->snake(), '{{singularModel}}' => str($modelName)->singular()->snake()],
                ['DummyPluralModel' => str($modelName)->plural(), '{{ pluralModel }}' => str($modelName)->plural(), '{{pluralModel}}' => str($modelName)->plural()],
                ['DummyColumns' => $columns, '{{ columns }}' => $columns, '{{columns}}' => $columns],
            ];

            foreach ($searches as $search) {
                $content = str_replace(array_keys($search), array_values($search), $content);
            }
        }

        return $content;
    }
}
