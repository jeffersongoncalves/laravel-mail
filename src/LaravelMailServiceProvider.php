<?php

namespace JeffersonGoncalves\LaravelMail;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\LaravelMail\Campaigns\CampaignReport;
use JeffersonGoncalves\LaravelMail\Commands\ListTemplatesCommand;
use JeffersonGoncalves\LaravelMail\Commands\MailCampaignCommand;
use JeffersonGoncalves\LaravelMail\Commands\MailStatsCommand;
use JeffersonGoncalves\LaravelMail\Commands\PruneMailLogsCommand;
use JeffersonGoncalves\LaravelMail\Commands\RetryFailedMailCommand;
use JeffersonGoncalves\LaravelMail\Commands\SendTestMailCommand;
use JeffersonGoncalves\LaravelMail\Commands\UnsuppressCommand;
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailComplained;
use JeffersonGoncalves\LaravelMail\Listeners\AddToSuppressionList;
use JeffersonGoncalves\LaravelMail\Listeners\CheckSuppression;
use JeffersonGoncalves\LaravelMail\Listeners\InjectTrackingPixel;
use JeffersonGoncalves\LaravelMail\Listeners\LogSendingMessage;
use JeffersonGoncalves\LaravelMail\Listeners\LogSentMessage;
use JeffersonGoncalves\LaravelMail\Services\MailStats;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mail')
            ->hasConfigFile()
            ->hasCommands([
                PruneMailLogsCommand::class,
                UnsuppressCommand::class,
                RetryFailedMailCommand::class,
                SendTestMailCommand::class,
                ListTemplatesCommand::class,
                MailStatsCommand::class,
                MailCampaignCommand::class,
            ])
            ->hasMigrations([
                'create_mail_logs_table',
                'create_mail_templates_table',
                'create_mail_template_versions_table',
                'create_mail_tracking_events_table',
                'create_mail_suppressions_table',
                'add_tags_to_mail_logs_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-mail', function () {
            return new LaravelMail;
        });

        $this->app->singleton('laravel-mail-stats', function () {
            return new MailStats;
        });

        $this->app->singleton(CampaignReport::class);
    }

    public function packageBooted(): void
    {
        if (config('laravel-mail.suppression.enabled', false)) {
            Event::listen(MessageSending::class, CheckSuppression::class);
        }

        if (config('laravel-mail.logging.enabled', true)) {
            Event::listen(MessageSending::class, LogSendingMessage::class);
            Event::listen(MessageSent::class, LogSentMessage::class);
        }

        $pixelOpenTracking = config('laravel-mail.tracking.pixel.open_tracking', false);
        $pixelClickTracking = config('laravel-mail.tracking.pixel.click_tracking', false);

        if ($pixelOpenTracking || $pixelClickTracking) {
            Event::listen(MessageSending::class, InjectTrackingPixel::class);
        }

        if (config('laravel-mail.suppression.enabled', false)) {
            Event::listen(MailBounced::class, AddToSuppressionList::class);
            Event::listen(MailComplained::class, AddToSuppressionList::class);
        }

        $this->registerWebhookRoutes();
        $this->registerPreviewRoutes();
        $this->registerTrackingRoutes();
    }

    protected function registerWebhookRoutes(): void
    {
        Route::prefix(config('laravel-mail.tracking.route_prefix', 'webhooks/mail'))
            ->middleware(config('laravel-mail.tracking.route_middleware', []))
            ->group(__DIR__.'/../routes/webhooks.php');
    }

    protected function registerPreviewRoutes(): void
    {
        Route::prefix(config('laravel-mail.preview.route_prefix', 'mail/preview'))
            ->middleware(config('laravel-mail.preview.route_middleware', ['web']))
            ->group(__DIR__.'/../routes/preview.php');
    }

    protected function registerTrackingRoutes(): void
    {
        Route::prefix(config('laravel-mail.tracking.pixel.route_prefix', 'mail/t'))
            ->middleware(config('laravel-mail.tracking.pixel.route_middleware', []))
            ->group(__DIR__.'/../routes/tracking.php');
    }
}
