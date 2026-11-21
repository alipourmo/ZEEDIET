<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ReportPublished;
use App\Listeners\AppointmentSmsSubscriber;
use App\Listeners\QueueReportReadySms;
use App\Services\Sms\KavenegarGateway;
use App\Services\Sms\LogGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The SMS provider is chosen by SMS_DRIVER (spec section 9).
        $this->app->bind(SmsGateway::class, function (): SmsGateway {
            return match ((string) config('services.sms.driver')) {
                'kavenegar' => new KavenegarGateway(
                    (string) config('services.sms.kavenegar.api_key'),
                    (string) config('services.sms.kavenegar.sender'),
                ),
                'log' => new LogGateway,
                default => throw new RuntimeException('SMS_DRIVER must be kavenegar or log.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catch N+1 queries outside production (spec section 16, S0-T1).
        Model::preventLazyLoading(! $this->app->isProduction());

        // Appointment SMS, and the report_ready SMS (spec section 9).
        Event::subscribe(AppointmentSmsSubscriber::class);
        Event::listen(ReportPublished::class, QueueReportReadySms::class);

        Blade::directive('fa', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::fa({$expression}); ?>");
        Blade::directive('jdate', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::jdate({$expression}); ?>");
        Blade::directive('jdatetime', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::jdatetime({$expression}); ?>");
        Blade::directive('money', static fn (string $expression): string => "<?php echo \\App\\Support\\Display::money({$expression}); ?>");
    }
}
