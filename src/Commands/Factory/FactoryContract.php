<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Commands\Factory;

use PeibinLaravel\Database\Commands\ModelOption;

interface FactoryContract
{
    /**
     * Create all tables.
     *
     * @param ModelOption $option
     * @return mixed
     */
    public function createModels(ModelOption $option);

    /**
     * Create the specified table.
     *
     * @param string      $table
     * @param ModelOption $option
     * @return mixed
     */
    public function createModel(string $table, ModelOption $option);
}
