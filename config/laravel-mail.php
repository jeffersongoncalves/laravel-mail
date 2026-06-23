<?php

use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;
use JeffersonGoncalves\LaravelMail\Models\MailTemplateVersion;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Mail Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, all outgoing emails will be logged to the database.
    | You can disable this in specific environments if needed.
    |
    */

    'logging' => [
        'enabled' => env('LARAVEL_MAIL_LOGGING_ENABLED', true),

        /*
        | Store the HTML body of the email in the database.
        | Disable to save storage space.
        */
        'store_html_body' => env('LARAVEL_MAIL_STORE_HTML', true),

        /*
        | Store the plain text body of the email in the database.
        */
        'store_text_body' => env('LARAVEL_MAIL_STORE_TEXT', true),

        /*
        | Store attachment metadata (name, size, content-type).
        | The actual attachment content is NOT stored unless store_attachment_files is enabled.
        */
        'store_attachments' => true,

        /*
        | Store actual attachment files to disk.
        | When enabled, files are saved to the configured disk and path.
        */
        'store_attachment_files' => env('LARAVEL_MAIL_STORE_ATTACHMENT_FILES', false),
        'attachments_disk' => env('LARAVEL_MAIL_ATTACHMENTS_DISK', 'local'),
        'attachments_path' => 'mail-attachments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Automatically delete old mail logs after the specified number of days.
    | Set to null to disable pruning.
    |
    */

    'prune' => [
        'enabled' => true,
        'older_than_days' => 30,

        /*
        | Per-status retention policies (overrides older_than_days when set).
        | Example: ['delivered' => 30, 'bounced' => 90, 'complained' => 365]
        */
        'policies' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Suppression List
    |--------------------------------------------------------------------------
    |
    | Automatically suppress email addresses that hard bounce or complain.
    | Suppressed addresses will be blocked from receiving future emails.
    |
    */

    'suppression' => [
        'enabled' => env('LARAVEL_MAIL_SUPPRESSION_ENABLED', false),
        'auto_suppress_hard_bounces' => true,
        'auto_suppress_complaints' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Tracking
    |--------------------------------------------------------------------------
    |
    | Configure webhook endpoints for delivery tracking from email providers.
    | Each provider requires its own signing secret for HMAC validation.
    |
    */

    'tracking' => [
        'enabled' => env('LARAVEL_MAIL_TRACKING_ENABLED', false),

        'route_prefix' => 'webhooks/mail',

        'route_middleware' => [],

        'providers' => [
            'ses' => [
                'enabled' => false,

                /*
                | Verify the SNS message signature on every incoming webhook.
                | When enabled, requests with a missing/invalid signature or a
                | SigningCertURL that is not a legitimate AWS SNS endpoint are
                | rejected (fail closed). Strongly recommended in production.
                */
                'verify_signature' => env('LARAVEL_MAIL_SES_VERIFY_SIGNATURE', false),
            ],
            'sendgrid' => [
                'enabled' => false,

                /*
                | ECDSA public key ("Verification Key") from the SendGrid Event
                | Webhook settings. When set, the X-Twilio-Email-Event-Webhook-*
                | signature is verified; missing/invalid signatures are rejected.
                */
                'verification_key' => env('LARAVEL_MAIL_SENDGRID_VERIFICATION_KEY'),
                'signing_secret' => env('LARAVEL_MAIL_SENDGRID_SIGNING_SECRET'),
            ],
            'postmark' => [
                'enabled' => false,
                'username' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_USERNAME'),
                'password' => env('LARAVEL_MAIL_POSTMARK_WEBHOOK_PASSWORD'),
            ],
            'mailgun' => [
                'enabled' => false,
                'signing_key' => env('LARAVEL_MAIL_MAILGUN_SIGNING_KEY'),
            ],
            'resend' => [
                'enabled' => false,
                'signing_secret' => env('LARAVEL_MAIL_RESEND_SIGNING_SECRET'),
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Pixel Tracking (Provider-Independent)
        |--------------------------------------------------------------------------
        |
        | Track email opens and clicks without relying on email provider webhooks.
        | Injects a 1x1 transparent pixel for open tracking and rewrites links
        | for click tracking. Works with any mailer including plain SMTP.
        |
        */

        'pixel' => [
            'open_tracking' => env('LARAVEL_MAIL_PIXEL_OPEN_TRACKING', false),
            'click_tracking' => env('LARAVEL_MAIL_PIXEL_CLICK_TRACKING', false),
            'route_prefix' => 'mail/t',
            'route_middleware' => [],

            /*
            | HMAC signing key for tracking URLs.
            | When null, the application key (APP_KEY) is used.
            */
            'signing_key' => env('LARAVEL_MAIL_PIXEL_SIGNING_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Configuration for database-backed email templates.
    |
    */

    'templates' => [
        'enabled' => true,

        /*
        | Default layout to wrap template content.
        | Set to null for no layout wrapping.
        */
        'default_layout' => null,

        /*
        | Inline CSS styles in template HTML for email client compatibility.
        | Uses tijsverkoyen/css-to-inline-styles (included with Laravel).
        */
        'inline_css' => env('LARAVEL_MAIL_INLINE_CSS', true),

        /*
        | List-Unsubscribe headers for Gmail/Yahoo compliance.
        | Use {email} placeholder in URL for recipient email substitution.
        */
        'unsubscribe' => [
            'enabled' => false,
            'url' => null,
            'mailto' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    |
    | Configuration for retrying failed or soft-bounced emails.
    |
    */

    'retry' => [
        'enabled' => env('LARAVEL_MAIL_RETRY_ENABLED', false),
        'max_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Preview
    |--------------------------------------------------------------------------
    |
    | Enable browser preview for mail logs and templates.
    | When signed_urls is enabled, preview links require a valid signature.
    |
    */

    'preview' => [
        'enabled' => env('LARAVEL_MAIL_PREVIEW_ENABLED', false),
        'route_prefix' => 'mail/preview',
        'route_middleware' => ['web'],
        'signed_urls' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | Enable tenant scoping for mail logs and templates.
    | When enabled, you must provide a tenant resolver callback.
    |
    */

    'tenant' => [
        'enabled' => false,
        'column' => 'tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Customize the database connection and table names used by the package.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Model Classes
    |--------------------------------------------------------------------------
    |
    | You can override the default models used by the package.
    |
    */

    'models' => [
        'mail_log' => MailLog::class,
        'mail_template' => MailTemplate::class,
        'mail_template_version' => MailTemplateVersion::class,
        'mail_tracking_event' => MailTrackingEvent::class,
        'mail_suppression' => MailSuppression::class,
    ],

];
