<?php

namespace App\Providers;

use App\Support\Brand;
use App\Support\Navigation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Nunca asignar atributos que no estén en $fillable: un campo extra en la petición falla en desarrollo.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Política de contraseñas. En producción también se rechazan las filtradas en
        // brechas conocidas (Have I Been Pwned; solo se envía un prefijo del hash).
        Password::defaults(fn () => Password::min(10)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));

        // Límite general de peticiones web por usuario (o por IP si no ha iniciado sesión).
        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip()));

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
