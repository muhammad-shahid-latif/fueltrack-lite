<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch N+1 queries, silently discarded attributes and typos during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Block migrate:fresh / db:wipe on the live server.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Paginator::useBootstrapFive();

        Blade::directive('money', fn (string $expression) => "<?php echo e(\App\Support\Format::money({$expression})); ?>");
        Blade::directive('liters', fn (string $expression) => "<?php echo e(\App\Support\Format::liters({$expression})); ?>");
        Blade::directive('qty', fn (string $expression) => "<?php echo e(\App\Support\Format::quantity({$expression})); ?>");
        Blade::directive('meter', fn (string $expression) => "<?php echo e(\App\Support\Format::meter({$expression})); ?>");
    }
}
