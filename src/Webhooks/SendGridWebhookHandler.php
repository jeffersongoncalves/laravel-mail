<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\WebhookSignatures\Verifiers\SendGridSignatureVerifier;

class SendGridWebhookHandler extends AbstractWebhookHandler
{
    protected function provider(): TrackingProvider
    {
        return TrackingProvider::SendGrid;
    }

    public function validate(Request $request): bool
    {
        // The "verification key" is the ECDSA public key SendGrid provides in the
        // Event Webhook settings. Fall back to the legacy `signing_secret` key.
        $verificationKey = config('laravel-mail.tracking.providers.sendgrid.verification_key')
            ?? config('laravel-mail.tracking.providers.sendgrid.signing_secret');

        // Signature verification is opt-in: when no key is configured we accept
        // the request (consistent with the other providers). When a key is set,
        // delegate the actual ECDSA (P-256/SHA-256) check to the shared
        // jeffersongoncalves/laravel-webhook-signatures verifier, which fails
        // closed on any missing header, malformed payload or invalid signature.
        if (! $verificationKey || ! is_string($verificationKey)) {
            return true;
        }

        return (new SendGridSignatureVerifier)->verify($request, $verificationKey);
    }

    public function handle(Request $request): void
    {
        $events = $request->all();

        // Handle both indexed array (SendGrid batch) and single event format
        $eventList = isset($events[0]) ? $events : [$events];

        foreach ($eventList as $event) {
            $this->processEvent($event);
        }
    }

    protected function processEvent(array $event): void
    {
        $messageId = $event['sg_message_id'] ?? null;

        if (! $messageId) {
            return;
        }

        // SendGrid sg_message_id contains a filter suffix, strip it
        $messageId = explode('.', $messageId)[0];

        $mailLog = $this->findMailLog($messageId) ?? $this->findMailLog("<{$messageId}>");

        if (! $mailLog) {
            return;
        }

        $eventType = $this->mapEventType($event['event'] ?? null);

        if (! $eventType) {
            return;
        }

        $occurredAt = isset($event['timestamp'])
            ? Carbon::createFromTimestamp($event['timestamp'])
            : null;

        $this->recordEvent(
            mailLog: $mailLog,
            type: $eventType,
            payload: $event,
            recipient: $event['email'] ?? null,
            url: $event['url'] ?? null,
            bounceType: $event['type'] ?? null,
            occurredAt: $occurredAt,
            providerEventId: $event['sg_event_id'] ?? null,
        );
    }

    protected function mapEventType(?string $event): ?TrackingEventType
    {
        return match ($event) {
            'delivered' => TrackingEventType::Delivered,
            'bounce', 'dropped' => TrackingEventType::Bounced,
            'spamreport' => TrackingEventType::Complained,
            'open' => TrackingEventType::Opened,
            'click' => TrackingEventType::Clicked,
            'deferred' => TrackingEventType::Deferred,
            default => null,
        };
    }
}
