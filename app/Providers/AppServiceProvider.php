<?php

namespace App\Providers;

use App\Support\Brand;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        View::composer(['layouts.guest', 'layouts.app'], function ($view): void {
            $view->with('brand', Brand::current());
        });

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user()?->loadMissing('role');

            $view->with([
                'currentUser' => $user,
                'menu' => Navigation::for($user),
            ]);
        });
    }
}
