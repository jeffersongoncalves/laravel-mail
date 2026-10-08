<?php

namespace JeffersonGoncalves\LaravelMail\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class LogSentMessage
{
    public function handle(MessageSent $event): void
    {
        $sentMessage = $event->sent;
        $message = $event->message;

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);

        $id = $this->extractHeaderValue($message, 'X-LaravelMail-ID');
        $templateId = $this->extractHeaderValue($message, 'X-LaravelMail-TemplateID');

        $data = [
            'mailer' => $event->data['__laravel_notification_mailer'] ?? config('mail.default'),
            'subject' => $message->getSubject(),
            'from' => $this->formatAddresses($message->getFrom()),
            'to' => $this->formatAddresses($message->getTo()),
            'cc' => $this->formatAddresses($message->getCc()),
            'bcc' => $this->formatAddresses($message->getBcc()),
            'reply_to' => $this->formatAddresses($message->getReplyTo()),
            'headers' => $this->extractHeaders($message),
            'status' => MailStatus::Sent,
        ];

        if (config('laravel-mail.logging.store_html_body', true)) {
            $data['html_body'] = $message->getHtmlBody();
        }

        if (config('laravel-mail.logging.store_text_body', true)) {
            $data['text_body'] = $message->getTextBody();
        }

        if (config('laravel-mail.logging.store_attachments', true)) {
            $data['attachments'] = $this->extractAttachments($message);
        }

        $providerMessageId = $sentMessage->getMessageId();
        if ($providerMessageId) {
            $data['provider_message_id'] = $providerMessageId;
        }

        $metadata = $event->data;
        unset($metadata['__laravel_notification_mailer'], $metadata['__laravel_notification']);

        if (! empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        if (config('laravel-mail.campaigns.enabled', false)) {
            $tags = $this->extractTags($message);
            if ($tags !== []) {
                $data['tags'] = $tags;
            }
        }

        if (config('laravel-mail.tenant.enabled', false)) {
            $tenantId = $event->data['__tenant_id'] ?? null;
            if ($tenantId) {
                $data[config('laravel-mail.tenant.column', 'tenant_id')] = $tenantId;
            }
        }

        if ($id) {
            $data['id'] = $id;
        }

        if ($templateId) {
            $data['mail_template_id'] = $templateId;
        }

        $modelClass::create($data);
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email: string, name: string}>|null
     */
    protected function formatAddresses(array $addresses): ?array
    {
        if (empty($addresses)) {
            return null;
        }

        return array_map(fn (Address $address) => [
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], $addresses);
    }

    /**
     * @return array<string, string>
     */
    protected function extractHeaders(Email $message): array
    {
        $headers = [];

        foreach ($message->getHeaders()->all() as $header) {
            $name = $header->getName();

            if (in_array(strtolower($name), ['to', 'from', 'cc', 'bcc', 'reply-to', 'subject', 'mime-version', 'content-type'])) {
                continue;
            }

            $headers[$name] = $header->getBodyAsString();
        }

        return $headers;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function extractAttachments(Email $message): ?array
    {
        $attachments = $message->getAttachments();

        if (empty($attachments)) {
            return null;
        }

        $storeFiles = config('laravel-mail.logging.store_attachment_files', false);
        $disk = config('laravel-mail.logging.attachments_disk', 'local');
        $basePath = config('laravel-mail.logging.attachments_path', 'mail-attachments');

        return array_map(function ($attachment) use ($storeFiles, $disk, $basePath) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('content-disposition', 'filename');

            $data = [
                'filename' => $filename,
                'content_type' => $headers->get('content-type')?->getBodyAsString(),
                'size' => strlen($attachment->getBody()),
            ];

            if ($storeFiles) {
                $storedPath = $basePath.'/'.date('Y/m/d').'/'.Str::uuid().'_'.($filename ?? 'attachment');
                Storage::disk($disk)->put($storedPath, $attachment->getBody());
                $data['path'] = $storedPath;
                $data['disk'] = $disk;
            }

            return $data;
        }, $attachments);
    }

    /**
     * Mail tags (Mailable::tag(), Envelope tags) — the campaign key for laravel-mail's campaign reports.
     *
     * @return list<string>
     */
    protected function extractTags(Email $message): array
    {
        $tags = [];

        foreach ($message->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
            }
        }

        return array_values(array_unique($tags));
    }

    protected function extractHeaderValue(Email $message, string $name): ?string
    {
        $header = $message->getHeaders()->get($name);

        return $header?->getBodyAsString();
    }
}
