<?php

use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Mail\TemplateMailable;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate;

// Concrete test mailable
class WelcomeTestMailable extends TemplateMailable
{
    public function __construct(
        public string $name = 'John',
    ) {}

    public function templateKey(): string
    {
        return 'welcome';
    }

    public function templateData(): array
    {
        return ['name' => $this->name];
    }

    protected function fallbackSubject(): string
    {
        return 'Fallback Welcome';
    }

    protected function fallbackContent(): Content
    {
        return new Content(
            htmlString: '<p>Fallback for '.$this->name.'</p>',
        );
    }
}

it('renders template from database', function () {
    MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome, {{ $name }}!'],
        'html_body' => ['en' => '<h1>Hello {{ $name }}</h1>'],
        'is_active' => true,
    ]);

    $mailable = new WelcomeTestMailable('Alice');

    Mail::fake();
    Mail::to('test@example.com')->send($mailable);

    Mail::assertSent(WelcomeTestMailable::class);
});

it('resolves template by key', function () {
    $template = MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome, {{ $name }}!'],
        'html_body' => ['en' => '<h1>Hello {{ $name }}</h1>'],
        'is_active' => true,
    ]);

    $mailable = new WelcomeTestMailable('Alice');
    $resolved = $mailable->resolveTemplate();

    expect($resolved)->not->toBeNull()
        ->and($resolved->key)->toBe('welcome');
});

it('returns null when template is inactive', function () {
    MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome!'],
        'html_body' => ['en' => '<h1>Hello</h1>'],
        'is_active' => false,
    ]);

    $mailable = new WelcomeTestMailable;
    $resolved = $mailable->resolveTemplate();

    expect($resolved)->toBeNull();
});

it('returns null when templates are disabled', function () {
    config()->set('laravel-mail.templates.enabled', false);

    MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome!'],
        'html_body' => ['en' => '<h1>Hello</h1>'],
        'is_active' => true,
    ]);

    $mailable = new WelcomeTestMailable;
    $resolved = $mailable->resolveTemplate();

    expect($resolved)->toBeNull();
});

it('allows injecting a template directly', function () {
    $template = MailTemplate::create([
        'key' => 'other-key',
        'name' => 'Other Template',
        'subject' => ['en' => 'Injected Subject'],
        'html_body' => ['en' => '<p>Injected</p>'],
        'is_active' => true,
    ]);

    $mailable = new WelcomeTestMailable;
    $mailable->useTemplate($template);

    expect($mailable->getMailTemplate()->key)->toBe('other-key');
});

it('uses fallback when no template exists', function () {
    $mailable = new WelcomeTestMailable('Bob');

    Mail::fake();
    Mail::to('test@example.com')->send($mailable);

    Mail::assertSent(WelcomeTestMailable::class);
});

it('respects locale for template content', function () {
    MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome!', 'pt_BR' => 'Bem-vindo!'],
        'html_body' => ['en' => '<h1>Hello {{ $name }}</h1>', 'pt_BR' => '<h1>Olá {{ $name }}</h1>'],
        'is_active' => true,
    ]);

    app()->setLocale('pt_BR');

    $mailable = new WelcomeTestMailable('Carlos');
    $template = $mailable->resolveTemplate();

    expect($template->getSubjectForLocale())->toBe('Bem-vindo!')
        ->and($template->getHtmlBodyForLocale())->toBe('<h1>Olá {{ $name }}</h1>');
});

it('sends the rendered text body of the template', function () {
    config()->set('mail.default', 'array');

    MailTemplate::create([
        'key' => 'welcome',
        'name' => 'Welcome Email',
        'subject' => ['en' => 'Welcome'],
        'html_body' => ['en' => '<h1>Hello {{ $name }}</h1>'],
        'text_body' => ['en' => 'Hello {{ $name }}, plain text.'],
        'is_active' => true,
    ]);

    Mail::to('jane@example.com')->send(new WelcomeTestMailable('Jane'));

    $message = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

    expect($message->getTextBody())->toBe('Hello Jane, plain text.')
        ->and($message->getHtmlBody())->toContain('Hello Jane');
});
