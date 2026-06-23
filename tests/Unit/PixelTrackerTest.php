<?php

use JeffersonGoncalves\LaravelMail\Services\PixelTracker;

beforeEach(function () {
    config()->set('laravel-mail.tracking.pixel.open_tracking', true);
    config()->set('laravel-mail.tracking.pixel.click_tracking', true);
    config()->set('laravel-mail.tracking.pixel.route_prefix', 'mail/t');
    config()->set('laravel-mail.tracking.pixel.signing_key', 'test-signing-key');
});

it('injects tracking pixel before closing body tag', function () {
    $tracker = new PixelTracker;
    $html = '<html><body><p>Hello</p></body></html>';

    $result = $tracker->injectTrackingPixel($html, 'test-uuid');

    expect($result)->toContain('<img src="')
        ->toContain('width="1" height="1"')
        ->toContain('style="display:none;width:1px;height:1px;border:0;"')
        ->toContain('</body></html>');

    $pixelPos = strpos($result, '<img src="');
    $bodyPos = strpos($result, '</body>');
    expect($pixelPos)->toBeLessThan($bodyPos);
});

it('appends tracking pixel when no body tag exists', function () {
    $tracker = new PixelTracker;
    $html = '<p>Hello World</p>';

    $result = $tracker->injectTrackingPixel($html, 'test-uuid');

    expect($result)->toContain('<p>Hello World</p>')
        ->toContain('<img src="');
});

it('rewrites http links in html', function () {
    $tracker = new PixelTracker;
    $html = '<a href="https://example.com">Click here</a>';

    $result = $tracker->rewriteLinks($html, 'test-uuid');

    expect($result)->toContain('mail/t/click/test-uuid')
        ->not->toContain('href="https://example.com"');
});

it('does not rewrite mailto links', function () {
    $tracker = new PixelTracker;
    $html = '<a href="mailto:test@example.com">Email us</a>';

    $result = $tracker->rewriteLinks($html, 'test-uuid');

    expect($result)->toContain('href="mailto:test@example.com"');
});

it('does not rewrite tel links', function () {
    $tracker = new PixelTracker;
    $html = '<a href="tel:+5511999999999">Call us</a>';

    $result = $tracker->rewriteLinks($html, 'test-uuid');

    expect($result)->toContain('href="tel:+5511999999999"');
});

it('does not rewrite anchor links', function () {
    $tracker = new PixelTracker;
    $html = '<a href="#section">Go to section</a>';

    $result = $tracker->rewriteLinks($html, 'test-uuid');

    expect($result)->toContain('href="#section"');
});

it('does not rewrite javascript links', function () {
    $tracker = new PixelTracker;
    $html = '<a href="javascript:alert(1)">XSS</a>';

    $result = $tracker->rewriteLinks($html, 'test-uuid');

    expect($result)->toContain('href="javascript:alert(1)"');
});

it('does not double-rewrite already tracked links', function () {
    $tracker = new PixelTracker;
    $html = '<a href="https://example.com">Link</a>';

    $result = $tracker->rewriteLinks($html, 'test-uuid');
    $result = $tracker->rewriteLinks($result, 'test-uuid');

    preg_match_all('/mail\/t\/click/', $result, $matches);
    expect($matches[0])->toHaveCount(1);
});

it('rewrites multiple links in html', function () {
    $tracker = new PixelTracker;
    $html = '<a href="https://one.com">One</a> <a href="https://two.com">Two</a>';

    $result = $tracker->rewriteLinks($html, 'test-uuid');

    expect($result)->toContain('mail/t/click/test-uuid')
        ->not->toContain('href="https://one.com"')
        ->not->toContain('href="https://two.com"');
});

it('generates valid pixel url with signature', function () {
    $tracker = new PixelTracker;
    $url = $tracker->generatePixelUrl('test-uuid');

    expect($url)->toContain('mail/t/pixel/test-uuid')
        ->toContain('sig=');
});

it('generates valid click url with encoded url and signature', function () {
    $tracker = new PixelTracker;
    $url = $tracker->generateClickUrl('test-uuid', 'https://example.com');

    expect($url)->toContain('mail/t/click/test-uuid')
        ->toContain('url='.base64_encode('https://example.com'))
        ->toContain('sig=');
});

it('verifies valid signature', function () {
    $tracker = new PixelTracker;
    $data = 'test-data';

    $url = $tracker->generatePixelUrl($data);
    preg_match('/sig=([^&]+)/', $url, $matches);
    $signature = $matches[1];

    expect($tracker->verifySignature($data, $signature))->toBeTrue();
});

it('rejects invalid signature', function () {
    $tracker = new PixelTracker;

    expect($tracker->verifySignature('test-data', 'invalid-signature'))->toBeFalse();
});

it('identifies safe urls', function () {
    expect(PixelTracker::isUrlSafe('https://example.com'))->toBeTrue();
    expect(PixelTracker::isUrlSafe('http://example.com'))->toBeTrue();
    expect(PixelTracker::isUrlSafe('https://example.com/path?q=1'))->toBeTrue();
});

it('identifies unsafe urls', function () {
    expect(PixelTracker::isUrlSafe('javascript:alert(1)'))->toBeFalse();
    expect(PixelTracker::isUrlSafe('data:text/html,<script>alert(1)</script>'))->toBeFalse();
    expect(PixelTracker::isUrlSafe('vbscript:MsgBox("xss")'))->toBeFalse();
    expect(PixelTracker::isUrlSafe('blob:http://example.com'))->toBeFalse();
});

it('rejects open-redirect style urls', function () {
    // Protocol-relative URLs would be parsed without a scheme and could redirect
    // the user to an arbitrary host.
    expect(PixelTracker::isUrlSafe('//evil.com'))->toBeFalse();
    expect(PixelTracker::isUrlSafe('//evil.com/path'))->toBeFalse();

    // Scheme-less (relative) URLs are no longer treated as safe.
    expect(PixelTracker::isUrlSafe('/path/to/page'))->toBeFalse();
    expect(PixelTracker::isUrlSafe('relative/page'))->toBeFalse();
    expect(PixelTracker::isUrlSafe(''))->toBeFalse();
});
