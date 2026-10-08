<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Campaigns\CampaignReport;

class MailCampaignCommand extends Command
{
    protected $signature = 'mail:campaign
        {tag? : show one campaign in detail, with its most clicked links}
        {--days=30 : only emails sent in the last N days (0 = all time)}
        {--links=10 : how many links to list}';

    protected $description = 'Show delivery, open and click rates per campaign (mail tag)';

    public function handle(CampaignReport $report): int
    {
        if (! config('laravel-mail.campaigns.enabled', false)) {
            $this->components->warn('Campaign tracking is off: set laravel-mail.campaigns.enabled and run the add_tags_to_mail_logs_table migration.');
        }

        $days = (int) $this->option('days');
        $from = $days > 0 ? Carbon::now()->subDays($days) : null;
        $tag = $this->argument('tag');

        return is_string($tag) ? $this->showCampaign($report, $tag, $from) : $this->listCampaigns($report, $from);
    }

    protected function listCampaigns(CampaignReport $report, ?Carbon $from): int
    {
        $campaigns = $report->all($from);

        if ($campaigns === []) {
            $this->components->info('No tagged emails in this period.');

            return self::SUCCESS;
        }

        $this->table(
            ['Campaign', 'Sent', 'Delivered', 'Opened', 'Clicked', 'Bounced', 'Complained'],
            array_map(fn ($stats) => [
                $stats->tag,
                $stats->sent,
                $stats->deliveryRate().'%',
                $stats->openRate().'%',
                $stats->clickRate().'%',
                $stats->bounceRate().'%',
                $stats->complaintRate().'%',
            ], $campaigns),
        );

        return self::SUCCESS;
    }

    protected function showCampaign(CampaignReport $report, string $tag, ?Carbon $from): int
    {
        $stats = $report->stats($tag, $from);

        $this->components->info("Campaign \"{$tag}\"");
        $this->table(['Metric', 'Count', 'Rate'], [
            ['Sent', $stats->sent, '-'],
            ['Delivered', $stats->delivered, $stats->deliveryRate().'%'],
            ['Opened', $stats->opened, $stats->openRate().'%'],
            ['Clicked', $stats->clicked, $stats->clickRate().'%'],
            ['Click-to-open', '-', $stats->clickToOpenRate().'%'],
            ['Bounced', $stats->bounced, $stats->bounceRate().'%'],
            ['Complained', $stats->complained, $stats->complaintRate().'%'],
        ]);

        if ($stats->links !== []) {
            $rows = [];
            foreach (array_slice($stats->links, 0, max(1, (int) $this->option('links')), true) as $url => $link) {
                $rows[] = [$url, $link['clicks'], $link['unique']];
            }
            $this->table(['Link', 'Clicks', 'Unique'], $rows);
        }

        return self::SUCCESS;
    }
}
