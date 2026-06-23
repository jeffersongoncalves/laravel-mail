<?php

namespace JeffersonGoncalves\LaravelMail\Services;

class PixelTracker
{
    public function injectTrackingPixel(string $html, string $mailLogId): string
    {
        $pixelUrl = $this->generatePixelUrl($mailLogId);
        $pixelTag = '<img src="'.htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8').'" width="1" height="1" alt="" style="display:none;width:1px;height:1px;border:0;" />';

        if (stripos($html, '</body>') !== false) {
            return (string) preg_replace('/<\/body>/i', $pixelTag.'</body>', $html, 1);
        }

        return $html.$pixelTag;
    }

    public function rewriteLinks(string $html, string $mailLogId): string
    {
        return (string) preg_replace_callback(
            '/<a\b([^>]*)\bhref\s*=\s*(["\'])([^"\']+)\2/i',
            function (array $matches) use ($mailLogId) {
                $prefix = $matches[1];
                $quote = $matches[2];
                $url = $matches[3];

                if ($this->shouldSkipUrl($url)) {
                    return $matches[0];
                }

                $clickUrl = $this->generateClickUrl($mailLogId, $url);

                return '<a'.$prefix.'href='.$quote.htmlspecialchars($clickUrl, ENT_QUOTES, 'UTF-8').$quote;
            },
            $html
        );
    }

    public function generatePixelUrl(string $mailLogId): string
    {
        $signature = $this->sign($mailLogId);
        $prefix = config('laravel-mail.tracking.pixel.route_prefix', 'mail/t');

        return url($prefix.'/pixel/'.$mailLogId.'?sig='.$signature);
    }

    public function generateClickUrl(string $mailLogId, string $originalUrl): string
    {
        $encodedUrl = base64_encode($originalUrl);
        $signature = $this->sign($mailLogId.$originalUrl);
        $prefix = config('laravel-mail.tracking.pixel.route_prefix', 'mail/t');

        return url($prefix.'/click/'.$mailLogId.'?url='.$encodedUrl.'&sig='.$signature);
    }

    public function verifySignature(string $data, string $signature): bool
    {
        $expected = $this->sign($data);

        return hash_equals($expected, $signature);
    }

    public static function isUrlSafe(string $url): bool
    {
        // Reject protocol-relative URLs (e.g. "//evil.com") which would
        // otherwise be parsed without a scheme and allow an open redirect.
        if (str_starts_with($url, '//')) {
            return false;
        }

        $parsed = parse_url($url);

        if ($parsed === false) {
            return false;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');

        // Only absolute http/https URLs are allowed. Scheme-less (relative) URLs
        // and dangerous pseudo-schemes are rejected to prevent open redirects.
        return in_array($scheme, ['http', 'https'], true);
    }

    protected function sign(string $data): string
    {
        $key = config('laravel-mail.tracking.pixel.signing_key') ?? config('app.key');

        return hash_hmac('sha256', $data, $key);
    }

    protected function shouldSkipUrl(string $url): bool
    {
        if (str_starts_with($url, '#')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme !== null && in_array(strtolower($scheme), ['mailto', 'tel', 'sms', 'javascript', 'data', 'vbscript'], true)) {
            return true;
        }

        $prefix = config('laravel-mail.tracking.pixel.route_prefix', 'mail/t');
        if (str_contains($url, $prefix.'/click/')) {
            return true;
        }

        return false;
    }
}
