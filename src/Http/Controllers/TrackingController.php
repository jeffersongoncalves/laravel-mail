<?php

namespace JeffersonGoncalves\LaravelMail\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Services\PixelTracker;
use JeffersonGoncalves\LaravelMail\Services\TrackingEventRecorder;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrackingController extends Controller
{
    /** @var string 43-byte transparent 1x1 GIF */
    private const TRANSPARENT_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function pixel(Request $request, string $mailLogId): Response
    {
        if (! config('laravel-mail.tracking.pixel.open_tracking', false)) {
            return $this->servePixel();
        }

        $signature = $request->query('sig', '');

        $tracker = new PixelTracker;

        if (! is_string($signature) || ! $tracker->verifySignature($mailLogId, $signature)) {
            return $this->servePixel();
        }

        $this->recordOpenEvent($mailLogId, $request);

        return $this->servePixel();
    }

    public function click(Request $request, string $mailLogId): RedirectResponse|Response
    {
        if (! config('laravel-mail.tracking.pixel.click_tracking', false)) {
            abort(404);
        }

        $encodedUrl = $request->query('url', '');
        $signature = $request->query('sig', '');

        if (! is_string($encodedUrl) || ! is_string($signature)) {
            abort(403);
        }

        $originalUrl = base64_decode($encodedUrl, true);

        if ($originalUrl === false) {
            abort(400);
        }

        $tracker = new PixelTracker;

        if (! $tracker->verifySignature($mailLogId.$originalUrl, $signature)) {
            abort(403);
        }

        if (! PixelTracker::isUrlSafe($originalUrl)) {
            abort(400);
        }

        $this->recordClickEvent($mailLogId, $originalUrl, $request);

        return new RedirectResponse($originalUrl, 302);
    }

    protected function recordOpenEvent(string $mailLogId, Request $request): void
    {
        if (! Str::isUuid($mailLogId)) {
            return;
        }

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);
        $mailLog = $modelClass::find($mailLogId);

        if (! $mailLog) {
            Log::debug('Pixel tracking: mail log not found', ['mail_log_id' => $mailLogId]);

            return;
        }

        $recorder = new TrackingEventRecorder;
        $recorder->record(
            mailLog: $mailLog,
            type: TrackingEventType::Opened,
            provider: TrackingProvider::Pixel,
            payload: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            occurredAt: now(),
        );
    }

    protected function recordClickEvent(string $mailLogId, string $originalUrl, Request $request): void
    {
        if (! Str::isUuid($mailLogId)) {
            return;
        }

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);
        $mailLog = $modelClass::find($mailLogId);

        if (! $mailLog) {
            Log::debug('Pixel tracking: mail log not found', ['mail_log_id' => $mailLogId]);

            return;
        }

        $recorder = new TrackingEventRecorder;
        $recorder->record(
            mailLog: $mailLog,
            type: TrackingEventType::Clicked,
            provider: TrackingProvider::Pixel,
            payload: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            url: $originalUrl,
            occurredAt: now(),
        );
    }

    protected function servePixel(): Response
    {
        return new Response(
            base64_decode(self::TRANSPARENT_GIF),
            200,
            [
                'Content-Type' => 'image/gif',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
            ]
        );
    }
}
