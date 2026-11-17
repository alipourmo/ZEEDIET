<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
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
        // Catch N+1 queries outside production (spec section 16, S0-T1).
        Model::preventLazyLoading(! $this->app->isProduction());

        Blade::directive('fa', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::fa({$expression}); ?>");
        Blade::directive('jdate', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::jdate({$expression}); ?>");
        Blade::directive('jdatetime', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::jdatetime({$expression}); ?>");
        Blade::directive('money', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::money({$expression}); ?>");
    }
}
