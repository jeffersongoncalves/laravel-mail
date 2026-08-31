<?php

namespace JeffersonGoncalves\LaravelMail\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use JeffersonGoncalves\LaravelMail\Actions\PreviewTemplateAction;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PreviewController extends Controller
{
    public function showMailLog(Request $request, string $mailLog): Response
    {
        if (! config('laravel-mail.preview.enabled', false)) {
            abort(404);
        }

        if (config('laravel-mail.preview.signed_urls', true) && ! $request->hasValidSignature()) {
            abort(403, 'Invalid signature.');
        }

        if (! Str::isUuid($mailLog)) {
            throw new NotFoundHttpException;
        }

        $modelClass = config('laravel-mail.models.mail_log', MailLog::class);
        $log = $modelClass::findOrFail($mailLog);

        $html = $log->html_body ?? '<p>No HTML body available.</p>';

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function showTemplate(Request $request, string $mailTemplate): Response
    {
        if (! config('laravel-mail.preview.enabled', false)) {
            abort(404);
        }

        if (config('laravel-mail.preview.signed_urls', true) && ! $request->hasValidSignature()) {
            abort(403, 'Invalid signature.');
        }

        if (! Str::isUuid($mailTemplate)) {
            throw new NotFoundHttpException;
        }

        $modelClass = config('laravel-mail.models.mail_template', MailTemplate::class);
        $template = $modelClass::findOrFail($mailTemplate);

        $action = new PreviewTemplateAction;
        $preview = $action->execute($template, [], $request->query('locale'));

        return new Response($preview['html'], 200, ['Content-Type' => 'text/html']);
    }
}
