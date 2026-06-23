<?php

use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

beforeEach(function () {
    config()->set('laravel-mail.tracking.enabled', true);
    config()->set('laravel-mail.tracking.providers.sendgrid.enabled', true);
});

it('processes SendGrid delivered event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-message-123',
    ]);

    $payload = [
        [
            'event' => 'delivered',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-message-123.filter001',
            'timestamp' => time(),
        ],
    ];

    $response = $this->postJson('/webhooks/mail/sendgrid', $payload);

    $response->assertOk();
    expect(MailTrackingEvent::count())->toBe(1);

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Delivered)
        ->and($event->provider)->toBe(TrackingProvider::SendGrid)
        ->and($event->recipient)->toBe('to@example.com');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes SendGrid bounce event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'bounce@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-bounce-123',
    ]);

    $payload = [
        [
            'event' => 'bounce',
            'email' => 'bounce@example.com',
            'sg_message_id' => 'sg-bounce-123.filter',
            'type' => 'bounce',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Bounced)
        ->and($event->bounce_type)->toBe('bounce');

    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Bounced);
});

it('processes SendGrid open event', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'sg-open-123',
    ]);

    $payload = [
        [
            'event' => 'open',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-open-123.filter',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Opened);

    // Open does not change mail log status
    $mailLog->refresh();
    expect($mailLog->status)->toBe(MailStatus::Delivered);
});

it('processes SendGrid click event with url', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
        'provider_message_id' => 'sg-click-123',
    ]);

    $payload = [
        [
            'event' => 'click',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-click-123.filter',
            'url' => 'https://example.com/link',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    $event = MailTrackingEvent::first();
    expect($event->type)->toBe(TrackingEventType::Clicked)
        ->and($event->url)->toBe('https://example.com/link');
});

it('processes multiple SendGrid events in one request', function () {
    $mailLog1 = MailLog::create([
        'subject' => 'Test 1',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-multi-1',
    ]);

    $mailLog2 = MailLog::create([
        'subject' => 'Test 2',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'other@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-multi-2',
    ]);

    $payload = [
        [
            'event' => 'delivered',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-multi-1.filter',
            'timestamp' => time(),
        ],
        [
            'event' => 'delivered',
            'email' => 'other@example.com',
            'sg_message_id' => 'sg-multi-2.filter',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertOk();

    expect(MailTrackingEvent::count())->toBe(2);
});

it('rejects SendGrid event without signature headers when verification key is set', function () {
    [, $publicKeyPem] = generateSendGridKeyPair();
    config()->set('laravel-mail.tracking.providers.sendgrid.verification_key', $publicKeyPem);

    $payload = [
        [
            'event' => 'delivered',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-signed.filter',
            'timestamp' => time(),
        ],
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload)->assertStatus(403);
    expect(MailTrackingEvent::count())->toBe(0);
});

it('rejects SendGrid event with a forged signature', function () {
    [, $publicKeyPem] = generateSendGridKeyPair();
    config()->set('laravel-mail.tracking.providers.sendgrid.verification_key', $publicKeyPem);

    $payload = [
        [
            'event' => 'delivered',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-forged.filter',
            'timestamp' => time(),
        ],
    ];

    $headers = [
        'X-Twilio-Email-Event-Webhook-Signature' => base64_encode('this-is-not-a-valid-signature'),
        'X-Twilio-Email-Event-Webhook-Timestamp' => (string) time(),
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload, $headers)->assertStatus(403);
    expect(MailTrackingEvent::count())->toBe(0);
});

it('accepts a SendGrid event with a valid ECDSA signature', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
        'provider_message_id' => 'sg-valid-sig',
    ]);

    [$privateKey, $publicKeyPem] = generateSendGridKeyPair();
    config()->set('laravel-mail.tracking.providers.sendgrid.verification_key', $publicKeyPem);

    $payload = [
        [
            'event' => 'delivered',
            'email' => 'to@example.com',
            'sg_message_id' => 'sg-valid-sig.filter',
            'timestamp' => time(),
        ],
    ];

    $timestamp = (string) time();
    $body = json_encode($payload);

    openssl_sign($timestamp.$body, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    $headers = [
        'X-Twilio-Email-Event-Webhook-Signature' => base64_encode($signature),
        'X-Twilio-Email-Event-Webhook-Timestamp' => $timestamp,
    ];

    $this->postJson('/webhooks/mail/sendgrid', $payload, $headers)->assertOk();

    expect(MailTrackingEvent::count())->toBe(1);
    expect(MailTrackingEvent::first()->type)->toBe(TrackingEventType::Delivered);
});
