<?php

declare(strict_types=1);

namespace PeibinLaravel\Database;

use Illuminate\Support\ServiceProvider;
use PeibinLaravel\Database\Commands\ModelCommand;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        ModelCommand::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->commands($this->commands);
    }
}
