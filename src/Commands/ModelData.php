<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Commands;

class ModelData
{
    protected array $columns = [];

    protected ?string $class = null;

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): self
    {
        $this->class = $class;
        return $this;
    }
}
