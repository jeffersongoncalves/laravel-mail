<?php

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Campaigns\CampaignReport;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

class CampaignTestMail extends Mailable
{
    /** @param  list<string>  $campaignTags */
    public function __construct(public array $campaignTags = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Promo', tags: $this->campaignTags);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Hi</p>');
    }
}

function campaignLog(array $tags, MailStatus $status = MailStatus::Sent, ?Carbon $createdAt = null): MailLog
{
    $log = MailLog::create([
        'subject' => 'Promo',
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => $status,
        'tags' => $tags,
    ]);

    if ($createdAt) {
        MailLog::where('id', $log->id)->update(['created_at' => $createdAt]);
    }

    return $log;
}

function trackEvent(MailLog $log, TrackingEventType $type, ?string $url = null): void
{
    MailTrackingEvent::create([
        'mail_log_id' => $log->id,
        'type' => $type,
        'provider' => TrackingProvider::Pixel,
        'url' => $url,
        'occurred_at' => now(),
    ]);
}

beforeEach(function () {
    config()->set('laravel-mail.campaigns.enabled', true);
    config()->set('mail.default', 'array');
});

it('records the mail tags on the log when campaigns are enabled', function () {
    Mail::to('ana@example.com')->send(new CampaignTestMail(['black-friday', 'newsletter']));

    expect(MailLog::query()->sole()->tags)->toBe(['black-friday', 'newsletter']);
});

it('does not record tags when campaigns are disabled', function () {
    config()->set('laravel-mail.campaigns.enabled', false);

    Mail::to('ana@example.com')->send(new CampaignTestMail(['black-friday']));

    expect(MailLog::query()->sole()->tags)->toBeNull();
});

it('reports delivery, open and click numbers per campaign', function () {
    $delivered = campaignLog(['black-friday'], MailStatus::Delivered);
    $opener = campaignLog(['black-friday'], MailStatus::Delivered);
    campaignLog(['black-friday'], MailStatus::Bounced);
    campaignLog(['black-friday'], MailStatus::Pending);
    $other = campaignLog(['welcome'], MailStatus::Delivered);

    trackEvent($delivered, TrackingEventType::Opened);
    trackEvent($delivered, TrackingEventType::Opened); // a second open of the same email counts once
    trackEvent($opener, TrackingEventType::Opened);
    trackEvent($delivered, TrackingEventType::Clicked, 'https://shop.test/deals');
    trackEvent($delivered, TrackingEventType::Clicked, 'https://shop.test/deals');
    trackEvent($opener, TrackingEventType::Clicked, 'https://shop.test/deals');
    trackEvent($opener, TrackingEventType::Clicked, 'https://shop.test/faq');
    trackEvent($other, TrackingEventType::Opened);

    $stats = app(CampaignReport::class)->stats('black-friday');

    expect($stats->sent)->toBe(3)
        ->and($stats->delivered)->toBe(2)
        ->and($stats->bounced)->toBe(1)
        ->and($stats->opened)->toBe(2)
        ->and($stats->clicked)->toBe(2)
        ->and($stats->openRate())->toBe(66.67)
        ->and($stats->clickToOpenRate())->toBe(100.0)
        ->and($stats->links)->toBe([
            'https://shop.test/deals' => ['clicks' => 3, 'unique' => 2],
            'https://shop.test/faq' => ['clicks' => 1, 'unique' => 1],
        ]);
});

it('lists the campaigns of a period', function () {
    campaignLog(['black-friday', 'newsletter']);
    campaignLog(['old-campaign'], createdAt: now()->subDays(60));
    campaignLog([]);

    $report = app(CampaignReport::class);

    expect($report->tags(now()->subDays(30)))->toEqualCanonicalizing(['black-friday', 'newsletter'])
        ->and($report->tags())->toContain('old-campaign')
        ->and(array_map(fn ($stats) => $stats->tag, $report->all(now()->subDays(30))))->toEqualCanonicalizing(['black-friday', 'newsletter']);
});

it('returns zero rates for an unknown campaign', function () {
    $stats = app(CampaignReport::class)->stats('nope');

    expect($stats->sent)->toBe(0)
        ->and($stats->openRate())->toBe(0.0)
        ->and($stats->clickToOpenRate())->toBe(0.0)
        ->and($stats->toArray())->toHaveKeys(['tag', 'open_rate', 'links']);
});

it('prints the campaign table and the campaign detail', function () {
    $log = campaignLog(['black-friday'], MailStatus::Delivered);
    trackEvent($log, TrackingEventType::Clicked, 'https://shop.test/deals');

    $this->artisan('mail:campaign')
        ->expectsOutputToContain('black-friday')
        ->assertSuccessful();

    $this->artisan('mail:campaign', ['tag' => 'black-friday'])
        ->expectsOutputToContain('https://shop.test/deals')
        ->assertSuccessful();
});
