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
use PeibinLaravel\Database\Commands\Factory\MongodbFactory;
use PeibinLaravel\Database\Commands\Factory\MysqlFactory;
use PeibinLaravel\Database\Utils\StandardPrettyPrinter;
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
        $option
            ->setDatabase($database)
            ->setPath($this->getOption('path', 'commands.gen:model.path', $database, 'app/Models'))
            ->setPrefix($this->getOption('prefix', 'prefix', $database, ''))
            ->setInheritance($this->getOption('inheritance', 'commands.gen:model.inheritance', $database, 'Model'))
            ->setUses(
                $this->getOption('uses', 'commands.gen:model.uses', $database, 'Illuminate\Database\Eloquent\Model')
            )
            ->setTableMapping($this->getOption('table-mapping', 'commands.gen:model.table_mapping', $database, []))
            ->setIgnoreTables($this->getOption('ignore-tables', 'commands.gen:model.ignore_tables', $database, []))
            ->setWithComments($this->getOption('with-comments', 'commands.gen:model.with_comments', $database, true));

        $builder = $this->getSchemaBuilder($option->getDatabase());
        $driver = $builder->getConnection()->getDriverName();
        $factory = $this->createFactory($driver);
        if ($table) {
            $factory->createModel($table, $option);
        } else {
            $factory->createModels($option);
        }
    }

    /**
     * Create a factory instance based on the configuration.
     *
     * @param string $driver
     * @return MongodbFactory|MysqlFactory
     */
    protected function createFactory(string $driver)
    {
        $drivers = [
            'mysql'   => new MysqlFactory($this->input, $this->output),
            'mongodb' => new MongodbFactory($this->input, $this->output),
        ];

        if (isset($drivers[$driver])) {
            return $drivers[$driver];
        }

        throw new \InvalidArgumentException("Unsupported driver [{$driver}].");
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
