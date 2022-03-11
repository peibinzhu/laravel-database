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

class ModelOption
{
    /**
     * @var string
     */
    protected $database;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var bool
     */
    protected $forceCasts;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $inheritance;

    /**
     * @var string
     */
    protected $uses;

    /**
     * @var bool
     */
    protected $withComments;

    /**
     * @var array
     */
    protected $tableMapping = [];

    /**
     * @var array
     */
    protected $ignoreTables = [];

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function setDatabase(string $database): self
    {
        $this->database = $database;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getInheritance(): string
    {
        return $this->inheritance;
    }

    public function setInheritance(string $inheritance): self
    {
        $this->inheritance = $inheritance;
        return $this;
    }

    public function getUses(): string
    {
        return $this->uses;
    }

    public function setUses(string $uses): self
    {
        $this->uses = $uses;
        return $this;
    }

    /**
     * @param string $table
     * @return object
     */
    public function getTableMapping(string $table): object
    {
        $mapping = new \stdClass();
        $mapping->class = null;
        $mapping->table = null;

        foreach ($this->tableMapping as $key => $mapTable) {
            if ($mapping->class) {
                break;
            }

            if (substr($key, -1) == '*') {
                $reg = sprintf('/%s(.*)/', substr($key, 0, strlen($key) - 1));
                if (preg_match($reg, $table, $matches)) {
                    [$class, $newTable] = array_pad(explode('|', $mapTable), 2, null);
                    $mapping->class = $class;
                    $mapping->table = $newTable;
                }
            } elseif ($key == $table) {
                $mapping->class = $table;
                break;
            }
        }
        return $mapping;
    }

    public function setTableMapping(array $tableMapping): self
    {
        foreach ($tableMapping as $item) {
            [$key, $name] = explode(':', $item);
            $this->tableMapping[$key] = $name;
        }

        return $this;
    }

    public function getIgnoreTables(): array
    {
        return $this->ignoreTables;
    }

    public function setIgnoreTables(array $ignoreTables): self
    {
        $this->ignoreTables = $ignoreTables;
        return $this;
    }

    public function isWithComments(): bool
    {
        return $this->withComments;
    }

    public function setWithComments(bool $withComments): self
    {
        $this->withComments = $withComments;
        return $this;
    }
}
