<?php

namespace App\Providers;

use App\Models\Farm;
use App\Observers\FarmObserver;
use App\Services\ActivityLogger;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Farm::observe(FarmObserver::class);

        // سجل النشاطات: كل إدخال يومي جديد يُسجَّل (التعديل والإلغاء تسجّلهما خدمة التصحيح)
        foreach (ActivityLogger::TYPES as $class) {
            $class::created(function ($model): void {
                app(ActivityLogger::class)->created($model, $model->getAttribute('created_by') ?? auth()->id());
            });
        }
    }
}
