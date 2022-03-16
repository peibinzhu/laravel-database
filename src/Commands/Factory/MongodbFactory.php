<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Commands\Factory;

use Illuminate\Support\Str;
use PeibinLaravel\Database\Commands\Ast\ModelUpdateVisitor;
use PeibinLaravel\Database\Commands\ModelOption;
use PeibinLaravel\Utils\CodeGen\Project;
use PhpParser\NodeTraverser;

class MongodbFactory extends Factory
{
    /**
     * Create all tables.
     *
     * @param ModelOption $option
     * @return mixed
     */
    public function createModels(ModelOption $option)
    {
        throw new \InvalidArgumentException('Creating all tables is not supported.');
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
                'columns' => [],
                'option'  => $option,
            ])
        );

        $stms = $traverser->traverse($stms);
        $code = $this->printer->prettyPrintFile($stms);
        file_put_contents($path, $code);

        $this->output->writeln(sprintf('<info>Model %s was created.</info>', $class));
    }
}
