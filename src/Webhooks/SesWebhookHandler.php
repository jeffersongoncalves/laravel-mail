<?php

namespace JeffersonGoncalves\LaravelMail\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;

class SesWebhookHandler extends AbstractWebhookHandler
{
    protected function provider(): TrackingProvider
    {
        return TrackingProvider::Ses;
    }

    public function validate(Request $request): bool
    {
        // SES sends SNS notifications. Validation is performed by verifying the
        // cryptographic signature SNS attaches to every message.
        $payload = $request->all();

        if (! isset($payload['Type'])) {
            return false;
        }

        // Signature verification is opt-in (consistent with the other providers,
        // which only verify when their credentials are configured). When enabled,
        // verification is real and fails closed on any missing/invalid field.
        if (! config('laravel-mail.tracking.providers.ses.verify_signature', false)) {
            return true;
        }

        return $this->verifySignature($payload);
    }

    /**
     * Verify the SNS message signature.
     *
     * NOTE: this verification is kept local rather than delegated to
     * jeffersongoncalves/laravel-webhook-signatures (SnsSignatureVerifier)
     * because that verifier's semantics differ from this handler's:
     *  - it requires an expected TopicArn as the secret and pins the message to
     *    that topic (laravel-mail has no TopicArn config to source it from);
     *  - it enforces a 1h timestamp tolerance, rejecting older-but-validly-signed
     *    notifications that this handler intentionally still accepts.
     * Delegating would change the security model and require additional config,
     * so the (already tested) local implementation is retained.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function verifySignature(array $payload): bool
    {
        // All fields required to reconstruct and verify the signature must be
        // present and be strings. A forged POST that omits any of them (e.g. no
        // SigningCertURL or no Signature) is rejected (fail closed).
        foreach (['Type', 'Signature', 'SignatureVersion', 'SigningCertURL', 'MessageId', 'Timestamp'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || $payload[$field] === '') {
                return false;
            }
        }

        if (! $this->isValidCertUrl($payload['SigningCertURL'])) {
            return false;
        }

        $canonical = $this->canonicalMessage($payload);

        if ($canonical === null) {
            return false;
        }

        $signature = base64_decode($payload['Signature'], true);

        if ($signature === false) {
            return false;
        }

        // SignatureVersion 1 uses SHA1, version 2 uses SHA256.
        $algorithm = $payload['SignatureVersion'] === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

        $certificate = rescue(fn () => (string) Http::get($payload['SigningCertURL'])->body(), null, false);

        if (! is_string($certificate) || $certificate === '') {
            return false;
        }

        $publicKey = openssl_pkey_get_public($certificate);

        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($canonical, $signature, $publicKey, $algorithm) === 1;
    }

    /**
     * Ensure the SigningCertURL points to a legitimate AWS SNS endpoint over
     * HTTPS before we ever download it.
     */
    protected function isValidCertUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        if (strtolower($parsed['scheme']) !== 'https') {
            return false;
        }

        $host = strtolower($parsed['host']);

        // Host must be sns.<region>.amazonaws.com (or the China partition).
        return (bool) preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/', $host);
    }

    /**
     * Build the canonical string SNS signs, field by field in the documented order.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function canonicalMessage(array $payload): ?string
    {
        $type = $payload['Type'];

        $fields = match ($type) {
            'Notification' => isset($payload['Subject'])
                ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
                : ['Message', 'MessageId', 'Timestamp', 'TopicArn', 'Type'],
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            default => null,
        };

        if ($fields === null) {
            return null;
        }

        $canonical = '';

        foreach ($fields as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field])) {
                return null;
            }

            $canonical .= $field."\n".$payload[$field]."\n";
        }

        return $canonical;
    }

    public function handle(Request $request): void
    {
        $payload = $request->all();

        // Handle SNS subscription confirmation
        if (($payload['Type'] ?? null) === 'SubscriptionConfirmation') {
            if (isset($payload['SubscribeURL'])) {
                rescue(fn () => Http::get($payload['SubscribeURL']));
            }

            return;
        }

        if (($payload['Type'] ?? null) !== 'Notification') {
            return;
        }

        $message = json_decode($payload['Message'] ?? '{}', true);
        $notificationType = $message['notificationType'] ?? $message['eventType'] ?? null;
        $mail = $message['mail'] ?? [];
        $messageId = $mail['messageId'] ?? null;

        if (! $messageId) {
            return;
        }

        // SES messageId is wrapped in angle brackets in the provider_message_id
        $mailLog = $this->findMailLog($messageId) ?? $this->findMailLog("<{$messageId}>");

        if (! $mailLog) {
            return;
        }

        $eventType = $this->mapEventType($notificationType);

        if (! $eventType) {
            return;
        }

        $recipient = $this->extractRecipient($message, $notificationType);
        $bounceType = null;

        if ($notificationType === 'Bounce') {
            $bounce = $message['bounce'] ?? [];
            $bounceType = ($bounce['bounceType'] ?? 'Undetermined').'/'.($bounce['bounceSubType'] ?? 'Undetermined');
        }

        $occurredAt = isset($mail['timestamp'])
            ? Carbon::parse($mail['timestamp'])
            : null;

        $this->recordEvent(
            mailLog: $mailLog,
            type: $eventType,
            payload: $message,
            recipient: $recipient,
            bounceType: $bounceType,
            occurredAt: $occurredAt,
            providerEventId: $payload['MessageId'] ?? null,
        );
    }

    protected function mapEventType(?string $type): ?TrackingEventType
    {
        return match ($type) {
            'Delivery' => TrackingEventType::Delivered,
            'Bounce' => TrackingEventType::Bounced,
            'Complaint' => TrackingEventType::Complained,
            'Open' => TrackingEventType::Opened,
            'Click' => TrackingEventType::Clicked,
            'DeliveryDelay' => TrackingEventType::Deferred,
            default => null,
        };
    }

    protected function extractRecipient(array $message, ?string $type): ?string
    {
        return match ($type) {
            'Delivery' => $message['delivery']['recipients'][0] ?? null,
            'Bounce' => $message['bounce']['bouncedRecipients'][0]['emailAddress'] ?? null,
            'Complaint' => $message['complaint']['complainedRecipients'][0]['emailAddress'] ?? null,
            'Open' => $message['open']['recipients'][0] ?? null,
            'Click' => $message['click']['recipients'][0] ?? null,
            default => null,
        };
    }
}
