<?php

namespace JeffersonGoncalves\LaravelMail\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;

/**
 * @property string $id
 * @property string|null $mailer
 * @property string|null $subject
 * @property array<int, array{email: string, name: string}>|null $from
 * @property array<int, array{email: string, name: string}>|null $to
 * @property array<int, array{email: string, name: string}>|null $cc
 * @property array<int, array{email: string, name: string}>|null $bcc
 * @property array<int, array{email: string, name: string}>|null $reply_to
 * @property string|null $html_body
 * @property string|null $text_body
 * @property array<string, string>|null $headers
 * @property array<int, array<string, mixed>>|null $attachments
 * @property array<string, mixed>|null $metadata
 * @property list<string>|null $tags campaign tags (when laravel-mail.campaigns.enabled)
 * @property MailStatus $status
 * @property string|null $provider_message_id
 * @property string|null $mailable_type
 * @property string|null $mailable_id
 * @property string|null $mail_template_id
 * @property string|null $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $mailable
 * @property-read string|null $preview_url
 * @property-read MailTemplate|null $template
 * @property-read Collection<int, MailTrackingEvent> $trackingEvents
 */
class MailLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'mailer',
        'subject',
        'from',
        'to',
        'cc',
        'bcc',
        'reply_to',
        'html_body',
        'text_body',
        'headers',
        'attachments',
        'metadata',
        'tags',
        'status',
        'provider_message_id',
        'mailable_type',
        'mailable_id',
        'mail_template_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'from' => 'array',
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'reply_to' => 'array',
            'headers' => 'array',
            'attachments' => 'array',
            'metadata' => 'array',
            'tags' => 'array',
            'status' => MailStatus::class,
        ];
    }

    public function getTable(): string
    {
        return config('laravel-mail.database.tables.mail_logs', parent::getTable());
    }

    public function getConnectionName(): ?string
    {
        return config('laravel-mail.database.connection') ?? parent::getConnectionName();
    }

    public function mailable(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            config('laravel-mail.models.mail_template', MailTemplate::class),
            'mail_template_id'
        );
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (! config('laravel-mail.preview.enabled', false)) {
            return null;
        }

        if (config('laravel-mail.preview.signed_urls', true)) {
            return URL::signedRoute('laravel-mail.preview.mail-log', ['mailLog' => $this->id]);
        }

        return route('laravel-mail.preview.mail-log', ['mailLog' => $this->id]);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(
            config('laravel-mail.models.mail_tracking_event', MailTrackingEvent::class),
            'mail_log_id'
        );
    }
}
