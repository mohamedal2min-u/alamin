<?php

namespace App\Providers;

use App\Models\Farm;
use App\Observers\FarmObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Farm::observe(FarmObserver::class);
    }
}
