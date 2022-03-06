<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace PeibinLaravel\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;
use PeibinLaravel\Database\Commands\Ast\ModelUpdateVisitor;
use PeibinLaravel\Database\Utils\StandardPrettyPrinter;
use PeibinLaravel\Utils\CodeGen\Project;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ModelCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'gen:model';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     */
    protected static $defaultName = 'gen:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Eloquent model class';

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * @var ConfigContract
     */
    protected $config;

    /**
     * @var Parser
     */
    protected $astParser;

    /**
     * @var StandardPrettyPrinter
     */
    protected $printer;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    /**
     * Run the console command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->resolver = $this->container->get(ConnectionResolverInterface::class);
        $this->config = $this->container->get(ConfigContract::class);
        $this->astParser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new StandardPrettyPrinter();

        return parent::run($input, $output);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->input->getArgument('table');
        $database = $this->input->getOption('database');

        $option = new ModelOption();
        $option->setDatabase($database)
            ->setPath($this->getOption('path', 'commands.gen:model.path', $database, 'app/Models'))
            ->setPrefix($this->getOption('prefix', 'prefix', $database, ''))
            ->setInheritance($this->getOption('inheritance', 'commands.gen:model.inheritance', $database, 'Model'))
            ->setUses(
                $this->getOption('uses', 'commands.gen:model.uses', $database, 'Illuminate\Database\Eloquent\Model')
            )
            ->setTableMapping($this->getOption('table-mapping', 'commands.gen:model.table_mapping', $database, []))
            ->setIgnoreTables($this->getOption('ignore-tables', 'commands.gen:model.ignore_tables', $database, []))
            ->setWithComments($this->getOption('with-comments', 'commands.gen:model.with_comments', $database, true));

        if ($table) {
            $this->createModel($table, $option);
        } else {
            $this->createModels($option);
        }
    }

    /**
     * Get the console command option.
     *
     * @param string $name
     * @param string $key
     * @param string $database
     * @param        $default
     * @return mixed
     */
    protected function getOption(string $name, string $key, string $database = 'default', $default = null)
    {
        $result = $this->input->getOption($name);
        $nonInput = null;
        if (in_array($name, ['with-comments'])) {
            $nonInput = false;
        }
        if (in_array($name, ['table-mapping', 'ignore-tables'])) {
            $nonInput = [];
        }

        if ($result === $nonInput) {
            $result = $this->config->get("database.connections.{$database}.{$key}", $default);
        }

        return $result;
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @param string $poolName
     * @return Builder
     */
    protected function getSchemaBuilder(string $poolName): Builder
    {
        $connection = $this->resolver->connection($poolName);
        return $connection->getSchemaBuilder();
    }

    /**
     * Create all tables.
     *
     * @param ModelOption $option
     */
    protected function createModels(ModelOption $option)
    {
        $builder = $this->getSchemaBuilder($option->getDatabase());
        $tables = [];

        foreach ($builder->getAllTables() as $row) {
            $row = (array)$row;
            $table = reset($row);
            if (!$this->isIgnoreTable($table, $option)) {
                $tables[] = $table;
            }
        }

        foreach ($tables as $table) {
            $this->createModel($table, $option);
        }
    }

    /**
     * Create the specified table.
     *
     * @param string      $table
     * @param ModelOption $option
     */
    protected function createModel(string $table, ModelOption $option)
    {
        $builder = $this->getSchemaBuilder($option->getDatabase());
        $table = Str::replaceFirst($option->getPrefix(), '', $table);

        $columns = $this->getColumnTypeListing($builder, $table);

        $project = new Project();
        $class = $option->getTableMapping()[$table] ?? Str::studly($table);

        $class = $project->namespace($option->getPath()) . $class;
        $path = base_path($project->path($class));

        if (!file_exists($path)) {
            $this->mkdir($path);
            file_put_contents($path, $this->buildClass($table, $class, $option));
        }

        $stms = $this->astParser->parse(file_get_contents($path));

        $traverser = new NodeTraverser();
        $traverser->addVisitor(
            $this->make(ModelUpdateVisitor::class, [
                'class'   => $class,
                'columns' => $columns,
                'option'  => $option,
            ])
        );

        $stms = $traverser->traverse($stms);
        $code = $this->printer->prettyPrintFile($stms);
        file_put_contents($path, $code);

        $this->output->writeln(sprintf('<info>Model %s was created.</info>', $class));
    }

    protected function getColumnTypeListing(Builder $builder, string $table): array
    {
        $connection = $builder->getConnection();
        $results = $connection->select(
            $this->compileColumnListing(),
            [$connection->getDatabaseName(), $connection->getTablePrefix() . $table]
        );

        return array_map(function ($result) {
            return (array)$result;
        }, $results);
    }

    protected function compileColumnListing(): string
    {
        return 'select `column_key` as `column_key`, `column_name` as `column_name`, `data_type` as `data_type`, `column_comment` as `column_comment`, `extra` as `extra`, `column_type` as `column_type` from information_schema.columns where `table_schema` = ? and `table_name` = ? order by ORDINAL_POSITION';
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array  $parameters
     * @return mixed
     */
    protected function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Is ignore table.
     *
     * @param string      $table
     * @param ModelOption $option
     * @return bool
     */
    protected function isIgnoreTable(string $table, ModelOption $option): bool
    {
        if (in_array($table, $option->getIgnoreTables())) {
            return true;
        }

        return $table === $this->config->get('databases.migrations', 'migrations');
    }

    /**
     * Format column's key to lower case.
     */
    protected function formatColumns(array $columns): array
    {
        return array_map(function ($item) {
            return array_change_key_case($item, CASE_LOWER);
        }, $columns);
    }

    /**
     * Create a directory.
     *
     * @param string $path
     */
    protected function mkdir(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass(string $table, string $name, ModelOption $option): string
    {
        $stub = file_get_contents(__DIR__ . '/stubs/Model.stub');

        return $this->replaceNamespace($stub, $name)
            ->replaceInheritance($stub, $option->getInheritance())
            ->replaceConnection($stub, $option->getDatabase())
            ->replaceUses($stub, $option->getUses())
            ->replaceClass($stub, $name)
            ->replaceTable($stub, $table);
    }


    /**
     * Replace the namespace for the given stub.
     */
    protected function replaceNamespace(string &$stub, string $name): self
    {
        $stub = str_replace(
            ['%NAMESPACE%'],
            [$this->getNamespace($name)],
            $stub
        );

        return $this;
    }

    /**
     * Get the full namespace for a given class, without the class name.
     */
    protected function getNamespace(string $name): string
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    protected function replaceInheritance(string &$stub, string $inheritance): self
    {
        $stub = str_replace(
            ['%INHERITANCE%'],
            [$inheritance],
            $stub
        );

        return $this;
    }

    protected function replaceConnection(string &$stub, string $connection): self
    {
        $stub = str_replace(
            ['%CONNECTION%'],
            [$connection],
            $stub
        );

        return $this;
    }

    protected function replaceUses(string &$stub, string $uses): self
    {
        $uses = $uses ? "use {$uses};" : '';
        $stub = str_replace(
            ['%USES%'],
            [$uses],
            $stub
        );

        return $this;
    }

    /**
     * Replace the class name for the given stub.
     */
    protected function replaceClass(string &$stub, string $name): self
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        $stub = str_replace('%CLASS%', $class, $stub);

        return $this;
    }

    /**
     * Replace the table name for the given stub.
     */
    protected function replaceTable(string $stub, string $table): string
    {
        return str_replace('%TABLE%', $table, $stub);
    }

    /**
     * Get the destination class path.
     */
    protected function getPath(string $name): string
    {
        return base_path(str_replace('\\', '/', $name) . '.php');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [
            [
                'table',
                InputArgument::OPTIONAL,
                'Which table you want to associated with the Model.',
            ],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            [
                'database',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Which connection database you want the Model use.',
                'mysql',
            ],
            ['path', null, InputOption::VALUE_OPTIONAL, 'The path that you want the Model file to be generated.',],
            ['prefix', 'P', InputOption::VALUE_OPTIONAL, 'What prefix that you want the Model set.',],
            ['inheritance', 'i', InputOption::VALUE_OPTIONAL, 'The inheritance that you want the Model extends.',],
            ['uses', 'U', InputOption::VALUE_OPTIONAL, 'The default class uses of the Model.',],
            [
                'table-mapping',
                'M',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Table mappings for model.',
            ],
            [
                'ignore-tables',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Ignore tables for creating models.',
            ],
            ['with-comments', null, InputOption::VALUE_NONE, 'Whether generate the property comments for model.',],
        ];
    }
}
