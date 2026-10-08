<?php

namespace JeffersonGoncalves\LaravelMail\Campaigns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

/**
 * Per-campaign numbers. A campaign is a mail tag — `$mailable->tag('black-friday')` or `tags: [...]` on the
 * Envelope — recorded on the mail log when `laravel-mail.campaigns.enabled` is on.
 */
class CampaignReport
{
    /**
     * Every tag sent in the period, most recent campaigns first.
     *
     * @return list<string>
     */
    public function tags(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tags = [];

        $this->logs($from, $to)
            ->whereNotNull('tags')
            ->latest()
            ->select(['id', 'tags', 'created_at'])
            ->each(function (MailLog $log) use (&$tags) {
                foreach ($log->tags ?? [] as $tag) {
                    $tags[$tag] = true;
                }
            });

        return array_keys($tags);
    }

    public function stats(string $tag, ?Carbon $from = null, ?Carbon $to = null): CampaignStats
    {
        /** @var array<string, int> $byStatus */
        $byStatus = $this->tagged($tag, $from, $to)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $count = fn (MailStatus ...$statuses) => array_sum(array_map(fn (MailStatus $s) => $byStatus[$s->value] ?? 0, $statuses));

        return new CampaignStats(
            tag: $tag,
            sent: array_sum($byStatus) - ($byStatus[MailStatus::Pending->value] ?? 0),
            delivered: $count(MailStatus::Delivered),
            bounced: $count(MailStatus::Bounced),
            complained: $count(MailStatus::Complained),
            opened: $this->distinctEmails($tag, TrackingEventType::Opened, $from, $to),
            clicked: $this->distinctEmails($tag, TrackingEventType::Clicked, $from, $to),
            links: $this->links($tag, $from, $to),
        );
    }

    /**
     * @return list<CampaignStats>
     */
    public function all(?Carbon $from = null, ?Carbon $to = null): array
    {
        return array_map(fn (string $tag) => $this->stats($tag, $from, $to), $this->tags($from, $to));
    }

    /**
     * @return array<string, array{clicks: int, unique: int}> most clicked first
     */
    protected function links(string $tag, ?Carbon $from, ?Carbon $to): array
    {
        $links = [];

        $this->events($tag, TrackingEventType::Clicked, $from, $to)
            ->whereNotNull('url')
            ->toBase()
            ->selectRaw('url, COUNT(*) as clicks, COUNT(DISTINCT mail_log_id) as uniques')
            ->groupBy('url')
            ->orderByDesc('clicks')
            ->get()
            ->each(function (object $row) use (&$links) {
                $links[(string) $row->url] = ['clicks' => (int) $row->clicks, 'unique' => (int) $row->uniques];
            });

        return $links;
    }

    protected function distinctEmails(string $tag, TrackingEventType $type, ?Carbon $from, ?Carbon $to): int
    {
        return $this->events($tag, $type, $from, $to)->distinct()->count('mail_log_id');
    }

    /**
     * @return Builder<MailTrackingEvent>
     */
    protected function events(string $tag, TrackingEventType $type, ?Carbon $from, ?Carbon $to): Builder
    {
        $modelClass = config('laravel-mail.models.mail_tracking_event', MailTrackingEvent::class);

        return $modelClass::query()
            ->where('type', $type)
            ->whereIn('mail_log_id', $this->tagged($tag, $from, $to)->select('id'));
    }

    /**
     * @return Builder<MailLog>
     */
    protected function tagged(string $tag, ?Carbon $from, ?Carbon $to): Builder
    {
        return $this->logs($from, $to)->whereJsonContains('tags', $tag);
    }

    /**
     * @return Builder<MailLog>
     */
    protected function logs(?Carbon $from, ?Carbon $to): Builder
    {
        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        return $modelClass::query()
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to));
    }
}
