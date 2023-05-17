<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Commands\Factory;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;
use PeibinLaravel\CodeParser\Project;
use PeibinLaravel\Database\Commands\Ast\ModelUpdateVisitor;
use PeibinLaravel\Database\Commands\ModelOption;
use PhpParser\NodeTraverser;

class MysqlFactory extends Factory
{
    /**
     * Create all tables.
     *
     * @param ModelOption $option
     */
    public function createModels(ModelOption $option)
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
    public function createModel(string $table, ModelOption $option)
    {
        $builder = $this->getSchemaBuilder($option->getDatabase());
        $table = Str::replaceFirst($option->getPrefix(), '', $table);

        $columns = $this->getColumnTypeListing($builder, $table);

        $project = new Project();
        $mapping = $option->getTableMapping($table);
        $class = $mapping->class ?: Str::studly($table);

        $class = $project->namespace($option->getPath()) . $class;
        $path = base_path($project->path($class));

        if (!file_exists($path)) {
            $this->mkdir($path);
            file_put_contents($path, $this->buildClass($mapping->table ?: $table, $class, $option));
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
}
