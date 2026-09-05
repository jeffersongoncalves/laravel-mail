<div class="filament-hidden">

![Laravel Mail](https://raw.githubusercontent.com/jeffersongoncalves/laravel-mail/master/art/jeffersongoncalves-laravel-mail.png)

</div>

# Laravel Mail

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jeffersongoncalves)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-mail.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-mail)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-mail/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-mail/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-mail/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-mail/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-mail.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-mail)

Complete email management for Laravel: logging, database templates with translation (spatie/laravel-translatable), delivery tracking via webhooks (SES, SendGrid, Postmark, Mailgun, Resend), pixel tracking (provider-independent open & click tracking), suppression list, inline CSS, List-Unsubscribe headers, browser preview, statistics, notification channel, retry, attachment storage, and analytics.

## Features

- **Email Logging** — Automatically logs all outgoing emails via `MessageSent` event
- **Database Templates** — Store email templates with Blade rendering and multi-locale translation via `spatie/laravel-translatable`
- **Template Versioning** — Automatic version history on every content change
- **Delivery Tracking** — Webhook handlers for 5 providers (SES, SendGrid, Postmark, Mailgun, Resend) with HMAC validation
- **Pixel Tracking** — Provider-independent open and click tracking via injected 1x1 transparent pixel and link rewriting (works with any mailer including plain SMTP)
- **Webhook Idempotency** — Duplicate webhook deliveries are detected and ignored via `provider_event_id`
- **Tracking Events** — Laravel events dispatched on delivery, bounce, complaint, open, click, and deferral
- **Suppression List** — Auto-suppress hard bounces and complaints, block sending to suppressed addresses
- **Inline CSS** — Automatic CSS inlining for email client compatibility (Outlook, Gmail, etc.)
- **List-Unsubscribe Headers** — Gmail/Yahoo compliance with `List-Unsubscribe` and `List-Unsubscribe-Post` headers
- **Browser Preview** — View sent emails and templates in the browser via signed URLs
- **Statistics** — Query helpers for sent, delivered, bounced, opened, clicked counts and daily aggregations
- **Notification Channel** — Send database templates via Laravel Notifications
- **Retry Failed Emails** — Retry failed or soft-bounced emails with max attempts control
- **Resend Emails** — Resend any previously sent email from the log
- **Attachment File Storage** — Optionally store email attachment files to disk (S3, local, etc.)
- **Pruning** — Artisan command to clean up old mail logs with per-status retention policies
- **Polymorphic Association** — Associate mail logs with any model via `HasMailLogs` trait
- **Multi-Tenant** — Optional tenant scoping for all tables
- **Customizable** — Override models, table names, and database connection
- **CLI Commands** — `mail:send-test`, `mail:templates`, `mail:stats`, `mail:prune`, `mail:retry`, `mail:unsuppress`

## Installation

```bash
composer require jeffersongoncalves/laravel-mail
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-mail-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="laravel-mail-config"
```

## Email Logging

Logging is enabled by default. Every email sent through Laravel's `Mail` facade is automatically logged to the `mail_logs` table.

```php
// Just send emails normally — they are logged automatically
Mail::to('user@example.com')->send(new WelcomeMail($user));
```

Each log entry captures: mailer, subject, from/to/cc/bcc/reply-to, HTML body, text body, headers, attachments metadata, and provider message ID.

When using `TemplateMailable`, the `mail_template_id` is automatically associated with the log entry.

### Disable Logging

```env
LARAVEL_MAIL_LOGGING_ENABLED=false
```

### Control What Gets Stored

```env
LARAVEL_MAIL_STORE_HTML=true
LARAVEL_MAIL_STORE_TEXT=true
```

## Database Templates

Create email templates in the database with multi-locale support via `spatie/laravel-translatable` and Blade rendering.

### Creating a Template

```php
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

MailTemplate::create([
    'key' => 'welcome',
    'name' => 'Welcome Email',
    'subject' => ['en' => 'Welcome, {{ $name }}!', 'pt_BR' => 'Bem-vindo, {{ $name }}!'],
    'html_body' => [
        'en' => '<h1>Hello {{ $name }}</h1><p>Welcome to our platform.</p>',
        'pt_BR' => '<h1>Ola {{ $name }}</h1><p>Bem-vindo a nossa plataforma.</p>',
    ],
    'variables' => [
        ['name' => 'name', 'type' => 'string', 'example' => 'John'],
    ],
]);
```

### Using Templates in Mailables

Extend `TemplateMailable` to create mailables that fetch content from the database:

```php
use JeffersonGoncalves\LaravelMail\Mail\TemplateMailable;
use Illuminate\Mail\Mailables\Content;

class WelcomeEmail extends TemplateMailable
{
    public function __construct(
        public User $user,
    ) {}

    public function templateKey(): string
    {
        return 'welcome';
    }

    public function templateData(): array
    {
        return ['name' => $this->user->name];
    }

    protected function fallbackSubject(): string
    {
        return 'Welcome!';
    }

    protected function fallbackContent(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: ['user' => $this->user],
        );
    }
}
```

### Translation API

The `MailTemplate` model uses `spatie/laravel-translatable`. You can access translations via:

```php
// Get for current locale
$template->subject; // Returns string for app()->getLocale()

// Get for specific locale
$template->getSubjectForLocale('pt_BR');
$template->getHtmlBodyForLocale('en');
$template->getTextBodyForLocale('es');

// Get all translations
$template->getTranslations('subject'); // ['en' => '...', 'pt_BR' => '...']

// Set translation
$template->setTranslation('subject', 'fr', 'Bienvenue !');
```

### Template Versioning

Every content change (subject, html_body, text_body) automatically creates a version snapshot:

```php
$template->update([
    'subject' => ['en' => 'Updated Welcome, {{ $name }}!'],
    'html_body' => ['en' => '<h1>New design for {{ $name }}</h1>'],
]);

// Version 2 is automatically created
$template->versions; // Collection of MailTemplateVersion
```

### Template Preview

Preview a template with example data without sending:

```php
use JeffersonGoncalves\LaravelMail\Actions\PreviewTemplateAction;

$action = new PreviewTemplateAction();
$preview = $action->execute($template, ['name' => 'Alice'], 'en');

// Returns: ['subject' => '...', 'html' => '...', 'text' => '...']
// HTML is automatically CSS-inlined when templates.inline_css is true
```

### Layouts

Wrap template content in a shared layout:

```php
// config/laravel-mail.php
'templates' => [
    'default_layout' => '<html><body>{!! $slot !!}</body></html>',
],

// Or per template
$template->update(['layout' => '<html><body>{!! $slot !!}</body></html>']);
```

## Inline CSS

Automatically inlines CSS styles for email client compatibility (Outlook, Gmail, Yahoo, etc.). Uses `tijsverkoyen/css-to-inline-styles` which is already included with Laravel.

```env
LARAVEL_MAIL_INLINE_CSS=true  # Enabled by default
```

When enabled, any `<style>` tags in your template HTML will be converted to inline `style` attributes on the corresponding elements. This applies to both `TemplateMailable` sending and `PreviewTemplateAction` rendering.

## List-Unsubscribe Headers

Add `List-Unsubscribe` and `List-Unsubscribe-Post` headers for Gmail/Yahoo compliance (required since 2024 for bulk senders).

```php
// config/laravel-mail.php
'templates' => [
    'unsubscribe' => [
        'enabled' => true,
        'url' => 'https://yourapp.com/unsubscribe/{email}', // {email} is replaced with recipient
        'mailto' => 'unsubscribe@yourapp.com',
    ],
],
```

When enabled, all emails sent via `TemplateMailable` will include:
- `List-Unsubscribe: <https://yourapp.com/unsubscribe/user%40example.com>, <mailto:unsubscribe@yourapp.com?subject=unsubscribe>`
- `List-Unsubscribe-Post: List-Unsubscribe=One-Click`

## Delivery Tracking via Webhooks

Track email delivery status in real-time via provider webhooks. Supports: **Amazon SES**, **SendGrid**, **Postmark**, **Mailgun**, and **Resend**.

### Enable Tracking

```env
LARAVEL_MAIL_TRACKING_ENABLED=true
```

### Configure Provider

Enable the providers you use in `config/laravel-mail.php`:

```php
'tracking' => [
    'enabled' => true,
    'route_prefix' => 'webhooks/mail',

    'providers' => [
        'ses' => [
            'enabled' => true,
        ],
        'sendgrid' => [
            'enabled' => true,
            'signing_secret' => env('LARAVEL_MAIL_SENDGRID_SIGNING_SECRET'),
        ],
        'postmark' => [
            'enabled' => true,
            'username' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_USERNAME'),
            'password' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_PASSWORD'),
        ],
        'mailgun' => [
            'enabled' => true,
            'signing_key' => env('LARAVEL_MAIL_MAILGUN_SIGNING_KEY'),
        ],
        'resend' => [
            'enabled' => true,
            'signing_secret' => env('LARAVEL_MAIL_RESEND_SIGNING_SECRET'),
        ],
    ],
],
```

### Webhook URLs

Configure these URLs in your email provider's dashboard:

| Provider | Webhook URL |
|----------|-------------|
| Amazon SES | `https://yourapp.com/webhooks/mail/ses` |
| SendGrid | `https://yourapp.com/webhooks/mail/sendgrid` |
| Postmark | `https://yourapp.com/webhooks/mail/postmark` |
| Mailgun | `https://yourapp.com/webhooks/mail/mailgun` |
| Resend | `https://yourapp.com/webhooks/mail/resend` |

### Tracked Events

| Event | Description | Updates Status | Laravel Event |
|-------|-------------|----------------|---------------|
| `delivered` | Email successfully delivered | `sent` -> `delivered` | `MailDelivered` |
| `bounced` | Email bounced (hard/soft) | -> `bounced` | `MailBounced` |
| `complained` | Recipient marked as spam | -> `complained` | `MailComplained` |
| `opened` | Email was opened | No | `MailOpened` |
| `clicked` | Link was clicked | No | `MailClicked` |
| `deferred` | Delivery was delayed | No | `MailDeferred` |

### Webhook Idempotency

Duplicate webhook deliveries from providers are automatically detected and ignored. Each webhook handler extracts a unique `provider_event_id` from the payload (e.g., SNS `MessageId` for SES, `sg_event_id` for SendGrid, `svix-id` for Resend). If an event with the same ID already exists, it is skipped — no duplicate tracking events, no duplicate Laravel events dispatched.

### Listening to Tracking Events

React to delivery events in your application:

```php
use JeffersonGoncalves\LaravelMail\Events\MailBounced;
use JeffersonGoncalves\LaravelMail\Events\MailComplained;

// In a listener or EventServiceProvider
Event::listen(MailBounced::class, function (MailBounced $event) {
    // $event->mailLog — the MailLog model
    // $event->trackingEvent — the MailTrackingEvent model
    Log::warning("Email bounced: {$event->trackingEvent->recipient}");
});

Event::listen(MailComplained::class, function (MailComplained $event) {
    // Disable the user's account, send alert, etc.
});
```

### Signature Validation

Each provider uses its own authentication method:

- **SES** — SNS certificate URL verification
- **SendGrid** — Timestamp-based validation (ECDSA)
- **Postmark** — HTTP Basic Auth
- **Mailgun** — HMAC SHA256 signature
- **Resend** — Svix HMAC SHA256 signature

When no signing secret is configured, validation is skipped (useful for development).

## Pixel Tracking (Provider-Independent)

Track email opens and clicks without relying on email provider webhooks. Works with any mailer including plain SMTP. The package injects a 1x1 transparent GIF pixel for open tracking and rewrites links for click tracking.

### Enable Pixel Tracking

```env
LARAVEL_MAIL_PIXEL_OPEN_TRACKING=true
LARAVEL_MAIL_PIXEL_CLICK_TRACKING=true
```

```php
// config/laravel-mail.php
'tracking' => [
    'pixel' => [
        'open_tracking' => true,   // Inject tracking pixel in emails
        'click_tracking' => true,  // Rewrite links for click tracking
        'route_prefix' => 'mail/t',
        'route_middleware' => [],
        'signing_key' => env('LARAVEL_MAIL_PIXEL_SIGNING_KEY'), // null = uses APP_KEY
    ],
],
```

### How It Works

1. When an email is sent, the `InjectTrackingPixel` listener modifies the HTML body:
   - **Open tracking**: Injects a `<img>` tag with a 1x1 transparent GIF before `</body>`
   - **Click tracking**: Rewrites all `<a href="...">` links to route through the package's click endpoint
2. When the recipient opens the email, the pixel image is loaded from your server, registering an `opened` event
3. When the recipient clicks a link, it passes through your server (registering a `clicked` event) and redirects to the original URL

### Tracking URLs

| Endpoint | Purpose |
|----------|---------|
| `GET /mail/t/pixel/{id}?sig={hmac}` | Serves 1x1 transparent GIF, records open event |
| `GET /mail/t/click/{id}?url={base64}&sig={hmac}` | Records click event, redirects to original URL |

All URLs are signed with HMAC-SHA256 to prevent forgery. Invalid signatures are silently ignored for pixel requests (still serves the GIF) and rejected with 403 for click requests.

### Security

- URLs are signed with HMAC-SHA256 using the configured `signing_key` (or `APP_KEY` as fallback)
- Click redirects validate that the target URL uses `http` or `https` schemes (blocks `javascript:`, `data:`, etc.)
- `mailto:`, `tel:`, `sms:`, and anchor (`#`) links are not rewritten
- Pixel responses include `Cache-Control: no-store` to prevent caching

### Coexistence with Provider Webhooks

Pixel tracking works alongside webhook-based tracking. Both register events in the `mail_tracking_events` table with different providers (`pixel` vs `ses`/`sendgrid`/etc.), and both dispatch the same Laravel events (`MailOpened`, `MailClicked`).

## Suppression List

Automatically suppress email addresses that hard bounce or receive spam complaints. Suppressed addresses are blocked from receiving future emails.

### Enable Suppression

```env
LARAVEL_MAIL_SUPPRESSION_ENABLED=true
```

```php
// config/laravel-mail.php
'suppression' => [
    'enabled' => true,
    'auto_suppress_hard_bounces' => true,
    'auto_suppress_complaints' => true,
],
```

When enabled, the package automatically:
- Adds hard-bounced addresses to the suppression list
- Adds complained addresses to the suppression list
- Blocks sending to any suppressed address (cancels the email before it's sent)

### Manual Suppression Management

```php
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;
use JeffersonGoncalves\LaravelMail\Enums\SuppressionReason;

// Manually suppress an address
MailSuppression::create([
    'email' => 'user@example.com',
    'reason' => SuppressionReason::Manual,
    'suppressed_at' => now(),
]);

// Check if an address is suppressed
$isSuppressed = MailSuppression::where('email', 'user@example.com')->exists();
```

### Unsuppress Command

```bash
php artisan mail:unsuppress user@example.com
```

## Browser Preview

View sent emails and templates directly in the browser.

### Enable Preview

```env
LARAVEL_MAIL_PREVIEW_ENABLED=true
```

```php
// config/laravel-mail.php
'preview' => [
    'enabled' => true,
    'route_prefix' => 'mail/preview',
    'route_middleware' => ['web'],
    'signed_urls' => true, // Require signed URLs for security
],
```

### Preview URLs

Access preview URLs via model accessors:

```php
$mailLog->preview_url;    // GET /mail/preview/mail-log/{id}?signature=...
$template->preview_url;   // GET /mail/preview/template/{id}?signature=...
```

When `signed_urls` is enabled, URLs are cryptographically signed and cannot be tampered with. When disabled, plain URLs are generated.

## Statistics

Query email statistics with the `MailStats` facade:

```php
use JeffersonGoncalves\LaravelMail\Facades\MailStats;
use Illuminate\Support\Carbon;

$from = Carbon::now()->subDays(30);
$to = Carbon::now();

MailStats::sent($from, $to);          // int
MailStats::delivered($from, $to);     // int
MailStats::bounced($from, $to);       // int
MailStats::complained($from, $to);    // int
MailStats::opened($from, $to);        // int (from tracking events)
MailStats::clicked($from, $to);       // int (from tracking events)
MailStats::deliveryRate($from, $to);  // float (percentage)
MailStats::bounceRate($from, $to);    // float (percentage)
MailStats::dailyStats($from, $to);    // Collection of daily aggregations
```

## Notification Channel

Send database templates via Laravel Notifications:

```php
use JeffersonGoncalves\LaravelMail\Channels\TemplateMailChannel;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function via($notifiable): array
    {
        return [TemplateMailChannel::class];
    }

    public function toTemplateMail($notifiable): array
    {
        return [
            'template_key' => 'welcome',
            'data' => ['name' => $notifiable->name],
            'locale' => 'en',
        ];
    }
}
```

## Retry Failed Emails

Retry emails that failed to send or soft-bounced.

### Enable Retry

```php
// config/laravel-mail.php
'retry' => [
    'enabled' => true,
    'max_attempts' => 3,
],
```

### Retry Programmatically

```php
use JeffersonGoncalves\LaravelMail\Actions\RetryFailedMailAction;

$action = new RetryFailedMailAction();
$action->execute($failedMailLog); // Returns true on success, false if max attempts reached
```

### Retry via Command

```bash
# Retry failed emails from the last 24 hours
php artisan mail:retry

# Retry soft-bounced emails from the last 48 hours
php artisan mail:retry --status=bounced --hours=48

# Limit the number of retries
php artisan mail:retry --limit=50
```

Hard bounces are automatically skipped when retrying bounced emails.

## Resend Emails

Resend any previously logged email:

```php
use JeffersonGoncalves\LaravelMail\Actions\ResendMailAction;

$action = new ResendMailAction();
$action->execute($mailLog);
```

## Attachment File Storage

Optionally store email attachment files to disk for later retrieval:

```env
LARAVEL_MAIL_STORE_ATTACHMENT_FILES=true
LARAVEL_MAIL_ATTACHMENTS_DISK=local   # or s3, etc.
```

```php
// config/laravel-mail.php
'logging' => [
    'store_attachment_files' => true,
    'attachments_disk' => 'local',
    'attachments_path' => 'mail-attachments',
],
```

When enabled, each attachment is stored to the configured disk and the `path` and `disk` are added to the attachment metadata in the mail log. When pruning, stored files are automatically cleaned up.

## Pruning Old Logs

Clean up old mail logs:

```bash
php artisan mail:prune            # Prune logs older than 30 days (default)
php artisan mail:prune --days=7   # Prune logs older than 7 days
```

### Per-Status Retention Policies

Keep different statuses for different durations:

```php
// config/laravel-mail.php
'prune' => [
    'enabled' => true,
    'older_than_days' => 30, // default fallback
    'policies' => [
        'delivered' => 30,   // delete delivered after 30 days
        'bounced' => 90,     // keep bounced for 90 days
        'complained' => 365, // keep complaints for 1 year
    ],
],
```

Schedule it:

```php
Schedule::command('mail:prune')->daily();
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `mail:prune` | Delete old mail logs (supports `--days` option) |
| `mail:retry` | Retry failed/bounced emails (supports `--status`, `--hours`, `--limit`) |
| `mail:unsuppress {email}` | Remove an email from the suppression list |
| `mail:send-test {key} {email}` | Send a test email using a template (supports `--locale`, `--data`) |
| `mail:templates` | List all mail templates in a table |
| `mail:stats` | Show email statistics (supports `--days`) |

### Send Test Email

```bash
# Send with example data from template variables
php artisan mail:send-test welcome user@example.com

# With specific locale
php artisan mail:send-test welcome user@example.com --locale=pt_BR

# With custom data
php artisan mail:send-test welcome user@example.com --data='{"name":"Alice"}'
```

### View Statistics

```bash
php artisan mail:stats            # Last 7 days (default)
php artisan mail:stats --days=30  # Last 30 days
```

### List Templates

```bash
php artisan mail:templates
```

## Polymorphic Association

Associate mail logs with any model:

```php
use JeffersonGoncalves\LaravelMail\Traits\HasMailLogs;

class User extends Model
{
    use HasMailLogs;
}

$user->mailLogs()->latest()->get();
```

## Multi-Tenancy

```php
// config/laravel-mail.php
'tenant' => [
    'enabled' => true,
    'column' => 'tenant_id',
],
```

## Custom Models

```php
// config/laravel-mail.php
'models' => [
    'mail_log' => \App\Models\MailLog::class,
    'mail_template' => \App\Models\MailTemplate::class,
    'mail_template_version' => \App\Models\MailTemplateVersion::class,
    'mail_tracking_event' => \App\Models\MailTrackingEvent::class,
    'mail_suppression' => \App\Models\MailSuppression::class,
],
```

## Custom Table Names

```php
// config/laravel-mail.php
'database' => [
    'connection' => null,
    'tables' => [
        'mail_logs' => 'mail_logs',
        'mail_templates' => 'mail_templates',
        'mail_template_versions' => 'mail_template_versions',
        'mail_tracking_events' => 'mail_tracking_events',
        'mail_suppressions' => 'mail_suppressions',
    ],
],
```

## Facades

```php
use JeffersonGoncalves\LaravelMail\Facades\LaravelMail;

LaravelMail::isLoggingEnabled();  // bool
LaravelMail::isTrackingEnabled(); // bool
LaravelMail::findByProviderMessageId('msg-id-123'); // ?MailLog
LaravelMail::updateStatus($mailLog, MailStatus::Delivered); // MailLog
```

```php
use JeffersonGoncalves\LaravelMail\Facades\MailStats;

MailStats::sent($from, $to);
MailStats::deliveryRate($from, $to);
MailStats::dailyStats($from, $to);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jefferson Goncalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
