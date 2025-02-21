<?php

namespace Habib\LaravelCrud\Commands;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'make:generate')]
class GenerateMakeCommand extends GeneratorCommand
{
    use CreatesMatchingTest;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new CRUD for a model';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Generate';

    /**
     * Execute the console command.
     *
     * @return void|bool
     *
     * @throws FileNotFoundException
     */
    public function handle()
    {
        if (parent::handle() === false && ! $this->option('force')) {
            if (! $this->confirm('Do you want to continue?', true)) {
                return;
            }
        }

        if ($this->option('all')) {
            $this->input->setOption('factory', true);
            $this->input->setOption('seed', true);
            $this->input->setOption('migration', true);
            $this->input->setOption('controller', true);
            $this->input->setOption('policy', true);
            $this->input->setOption('resource', true);
            $this->input->setOption('repository', true);
            $this->input->setOption('requests', true);
            $this->input->setOption('datatable', true);
        }

        if ($this->option('factory')) {
            $this->createFactory();
        }

        if ($this->option('migration')) {
            $this->createMigration();
        }

        if ($this->option('seed')) {
            $this->createSeeder();
        }

        if ($this->option('controller') || $this->option('resource') || $this->option('api')) {
            $this->createController();
        }

        if ($this->option('policy')) {
            $this->createPolicy();
        }

        if ($this->option('repository')) {
            $this->createRepository();
        }

        if ($this->option('requests')) {
            $this->createRequests();
        }

        if ($this->option('datatable')) {
            $this->createDatatable();
        }

        $this->createViews();
    }

    protected function createViews(): void
    {
        $viewTypes = ['form', 'action', 'filter'];
        $viewContents = [
            'form' => '<div class="grid gap-6 mb-6 md:grid-cols-2 w-full"></div>',
            'action' => "@include('dashboard::crud.action')",
            'filter' => '<x-dashboard::form.default :model="request()->all()" method="GET" :action="request()->fullUrl()"></x-dashboard::form.default>',
        ];

        foreach ($viewTypes as $viewType) {
            if ($viewName = $this->ask('What is the name of the view?', $viewType)) {
                $path = str($this->argument('name'))->snake()->prepend(resource_path('views/dashboard/'));
                app(Filesystem::class)->ensureDirectoryExists($path);

                $path = $path->append("/$viewName.blade.php");

                if (! file_exists($path)) {
                    file_put_contents($path, $viewContents[$viewType]);
                } else {
                    $this->info('View already exists');
                }
            }
        }
    }

    public function createRequests(): void
    {
        $request = Str::studly(class_basename($this->argument('name')));

        $this->call('make:request', [
            'name' => "Dashboard/{$request}/Store{$request}Request",
        ]);

        $this->call('make:request', [
            'name' => "Dashboard/{$request}/Update{$request}Request",
        ]);
    }

    /**
     * Create a model factory for the model.
     *
     * @return void
     */
    protected function createFactory()
    {
        $factory = Str::studly($this->argument('name'));

        $this->call('make:factory', [
            'name' => "{$factory}Factory",
            '--model' => $this->qualifyClass($this->getNameInput()),
        ]);
    }

    /**
     * Create a migration file for the model.
     *
     * @return void
     */
    protected function createMigration()
    {
        $table = Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));

        if ($this->option('pivot')) {
            $table = Str::singular($table);
        }

        $this->call('make:migration', [
            'name' => "create_{$table}_table",
            '--create' => $table,
        ]);
    }

    /**
     * Create a seeder file for the model.
     *
     * @return void
     */
    protected function createSeeder()
    {
        $seeder = Str::studly(class_basename($this->argument('name')));

        $this->call('make:seeder', [
            'name' => "{$seeder}Seeder",
        ]);
    }

    /**
     * Create a controller for the model.
     *
     * @return void
     */
    protected function createController()
    {
        $controller = Str::studly(class_basename($this->argument('name')));

        $modelName = $this->qualifyClass($this->getNameInput());

        $this->call('make:controller', array_filter([
            'name' => "Dashboard\\{$controller}\\{$controller}Controller",
            '--model' => $this->option('resource') || $this->option('api') ? $modelName : null,
            '--api' => $this->option('api'),
            '--test' => $this->option('test'),
            '--pest' => $this->option('pest'),
            '--type' => 'crud',
        ]));

    }

    /**
     * Create a policy file for the model.
     *
     * @return void
     */
    protected function createPolicy()
    {
        $policy = Str::studly(class_basename($this->argument('name')));

        $this->call('make:policy', [
            'name' => $name = "{$policy}Policy",
            '--model' => $this->qualifyClass($this->getNameInput()),
        ]);
        $authServiceProvider = file_get_contents(app_path('Providers/AuthServiceProvider.php'));
        $model = $this->qualifyModel($this->getNameInput());
        $name = $this->qualifyClass("App\\Policies\\$name");
        // check if the repository service provider already contains the repository
        if (str_contains($authServiceProvider, $model)) {
            return;
        }
        // if the array is empty, add the first element
        if (str_contains($authServiceProvider, 'protected array $policies = []')) {
            $authServiceProvider = str_replace(
                'protected $policies = []',
                "protected \$policies = [\n        \\$model::class => \\$name::class,",
                $authServiceProvider
            );
        }
        // if the array is not empty, add the element to the end of the array
        else {
            $authServiceProvider = str_replace(
                'protected $policies = [',
                "protected \$policies = [\n        \\$model::class => \\$name::class,",
                $authServiceProvider
            );
        }

        file_put_contents(
            app_path('Providers/AuthServiceProvider.php'),
            $authServiceProvider
        );
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('pivot')) {
            return $this->resolveStubPath('/stubs/model.pivot.stub');
        }

        if ($this->option('morph-pivot')) {
            return $this->resolveStubPath('/stubs/model.morph-pivot.stub');
        }

        return $this->resolveStubPath('/stubs/model.stub');
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
            : __DIR__.'../../stubs/'.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return is_dir(app_path('Models')) ? $rootNamespace.'\\Models' : $rootNamespace;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a migration, seeder, factory, policy, resource controller, and form request classes for the model'],
            ['controller', 'c', InputOption::VALUE_NONE, 'Create a new controller for the model'],
            ['factory', 'f', InputOption::VALUE_NONE, 'Create a new factory for the model'],
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists'],
            ['migration', 'm', InputOption::VALUE_NONE, 'Create a new migration file for the model'],
            ['morph-pivot', null, InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom polymorphic intermediate table model'],
            ['policy', null, InputOption::VALUE_NONE, 'Create a new policy for the model'],
            ['seed', 's', InputOption::VALUE_NONE, 'Create a new seeder for the model'],
            ['pivot', 'p', InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Indicates if the generated controller should be a resource controller'],
            ['repository', 'e', InputOption::VALUE_NONE, 'Create a new repository for the model'],
            ['api', null, InputOption::VALUE_NONE, 'Indicates if the generated controller should be an API resource controller'],
            ['requests', 'R', InputOption::VALUE_NONE, 'Create new form request classes and use them in the resource controller'],
            ['datatable', 'D', InputOption::VALUE_NONE, 'Create a new datatable for the model'],
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        if ($this->isReservedName($this->getNameInput()) || $this->didReceiveOptions($input)) {
            return;
        }

        collect(multiselect('Would you like any of the following?', [
            'seed' => 'Database Seeder',
            'factory' => 'Factory',
            'requests' => 'Form Requests',
            'migration' => 'Migration',
            'policy' => 'Policy',
            'resource' => 'Resource Controller',
            'repository' => 'Repository',
            'datatable' => 'Datatable',
        ]))->each(fn ($option) => $input->setOption($option, true));
    }

    private function createRepository(): void
    {
        $repository = Str::studly(class_basename($this->argument('name')));

        $this->call('make:repository', [
            'name' => "{$repository}/{$repository}Repository",
            '--model' => $this->qualifyClass($this->getNameInput()),
            '--interface' => true,
        ]);
    }

    private function createDatatable(): void
    {
        $datatable = Str::studly(class_basename($this->argument('name')));
        $model = $this->qualifyModel($this->getNameInput());

        $this->call('datatables:make', [
            'name' => "Dashboard/{$datatable}/{$datatable}Datatable",
            '--model' => $datatable,
            '--action' => 'dashboard::'.\str($this->argument('name'))->snake()->append('.action'),
            '--table' => (new $model)->getTable(),
        ]);
    }
}
