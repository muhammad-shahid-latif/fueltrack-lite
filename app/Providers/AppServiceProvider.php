<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
    }
}
