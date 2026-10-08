<?php

namespace JeffersonGoncalves\LaravelMail\Campaigns;

/**
 * Totals of one campaign (tag). Opens and clicks count distinct emails, so the rates read as "% of sent emails".
 */
final class CampaignStats
{
    /**
     * @param  array<string, array{clicks: int, unique: int}>  $links  url => total clicks / distinct emails that clicked it
     */
    public function __construct(
        public readonly string $tag,
        public readonly int $sent,
        public readonly int $delivered,
        public readonly int $bounced,
        public readonly int $complained,
        public readonly int $opened,
        public readonly int $clicked,
        public readonly array $links = [],
    ) {}

    public function rate(int $count): float
    {
        return $this->sent === 0 ? 0.0 : round($count / $this->sent * 100, 2);
    }

    public function deliveryRate(): float
    {
        return $this->rate($this->delivered);
    }

    public function bounceRate(): float
    {
        return $this->rate($this->bounced);
    }

    public function complaintRate(): float
    {
        return $this->rate($this->complained);
    }

    public function openRate(): float
    {
        return $this->rate($this->opened);
    }

    public function clickRate(): float
    {
        return $this->rate($this->clicked);
    }

    /** Clicks among the emails that were opened. */
    public function clickToOpenRate(): float
    {
        return $this->opened === 0 ? 0.0 : round($this->clicked / $this->opened * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tag' => $this->tag,
            'sent' => $this->sent,
            'delivered' => $this->delivered,
            'bounced' => $this->bounced,
            'complained' => $this->complained,
            'opened' => $this->opened,
            'clicked' => $this->clicked,
            'delivery_rate' => $this->deliveryRate(),
            'bounce_rate' => $this->bounceRate(),
            'complaint_rate' => $this->complaintRate(),
            'open_rate' => $this->openRate(),
            'click_rate' => $this->clickRate(),
            'click_to_open_rate' => $this->clickToOpenRate(),
            'links' => $this->links,
        ];
    }
}
