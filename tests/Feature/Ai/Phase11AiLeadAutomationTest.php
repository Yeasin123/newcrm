<?php

use App\Livewire\Ai\AutomationDashboard;
use App\Mail\NewLeadWelcomeMail;
use App\Models\LeadAutomationLog;
use App\Models\LeadDetail;
use App\Models\User;
use App\Services\Ai\ClaudeLlmGateway;
use App\Services\Ai\LlmGateway;
use App\Services\Ai\NullLlmGateway;
use App\Services\Lead\LeadAutomationService;
use App\Services\Lead\LeadWelcomeAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['ai.enabled' => false, 'ai.welcome_email.enabled' => false, 'ai.welcome_email.only_campaign_ids' => []]);
});

function claudeText(string $text): array
{
    return ['content' => [['type' => 'text', 'text' => $text]], 'stop_reason' => 'end_turn'];
}

test('the LLM gateway is Null until enabled with a key', function () {
    expect(app(LlmGateway::class))->toBeInstanceOf(NullLlmGateway::class)
        ->and(app(LlmGateway::class)->available())->toBeFalse();

    config(['ai.enabled' => true, 'ai.claude.api_key' => 'sk-test']);
    expect(app(LlmGateway::class))->toBeInstanceOf(ClaudeLlmGateway::class)
        ->and(app(LlmGateway::class)->available())->toBeTrue();
});

test('the Claude gateway parses a JSON reply, tolerating a code fence', function () {
    config(['ai.enabled' => true, 'ai.claude.api_key' => 'sk-test']);
    Http::fake(['*api.anthropic.com/*' => Http::response(
        claudeText('```json'."\n".'{"subject":"Welcome","body":"Hello there"}'."\n".'```'), 200,
    )]);

    expect(app(LlmGateway::class)->generateJson('sys', 'prompt'))
        ->toBe(['subject' => 'Welcome', 'body' => 'Hello there']);
});

test('the Claude gateway throws on a refusal stop reason', function () {
    config(['ai.enabled' => true, 'ai.claude.api_key' => 'sk-test']);
    Http::fake(['*api.anthropic.com/*' => Http::response(['content' => [], 'stop_reason' => 'refusal'], 200)]);

    expect(fn () => app(LlmGateway::class)->generateText('s', 'p'))->toThrow(RuntimeException::class);
});

test('the welcome agent uses the fixed fallback whenever the LLM is unavailable or invalid', function () {
    $lead = LeadDetail::factory()->create(['first_name' => 'Sam', 'last_name' => 'Lee']);

    // unavailable
    $copy = app(LeadWelcomeAgentService::class)->compose($lead);
    expect($copy['is_ai_generated'])->toBeFalse()
        ->and($copy['reason'])->toBe('llm_unavailable')
        ->and($copy['subject'])->toBe('Thank you for contacting us')
        ->and($copy['body'])->toContain('Hi Sam Lee,');

    // available but returns a too-short body -> validation_failed -> fallback
    config(['ai.enabled' => true, 'ai.claude.api_key' => 'sk-test']);
    Http::fake(['*api.anthropic.com/*' => Http::response(claudeText('{"subject":"Hi","body":"too short"}'), 200)]);
    $copy = app(LeadWelcomeAgentService::class)->compose($lead->fresh());
    expect($copy['is_ai_generated'])->toBeFalse()->and($copy['reason'])->toBe('validation_failed');
});

test('the welcome agent returns AI copy when the reply validates', function () {
    config(['ai.enabled' => true, 'ai.claude.api_key' => 'sk-test']);
    Http::fake(['*api.anthropic.com/*' => Http::response(claudeText(json_encode([
        'subject' => 'Welcome to American Home Coverage',
        'body' => 'Hi Sam, thanks so much for reaching out to us today about home coverage. A member of our team will follow up shortly with the details you need.',
    ])), 200)]);

    $copy = app(LeadWelcomeAgentService::class)->compose(LeadDetail::factory()->create());

    expect($copy['is_ai_generated'])->toBeTrue()
        ->and($copy['reason'])->toBe('llm_generated')
        ->and($copy['subject'])->toBe('Welcome to American Home Coverage');
});

test('the automation is duplicate-safe and skips with a logged reason', function () {
    Mail::fake();
    $svc = app(LeadAutomationService::class);

    // disabled -> skipped(automation_disabled), no mail
    $lead = LeadDetail::factory()->create(['email' => 'sam@example.com']);
    expect($svc->runWelcomeEmail($lead))->toBe('skipped');
    $log = LeadAutomationLog::firstOrFail();
    expect($log->status)->toBe('skipped')->and($log->meta['skip_reason'])->toBe('automation_disabled');
    Mail::assertNothingSent();

    // enable; a lead with no email -> skipped(no_valid_email)
    config(['ai.welcome_email.enabled' => true]);
    $noEmail = LeadDetail::factory()->create(['email' => null]);
    expect($svc->runWelcomeEmail($noEmail))->toBe('skipped');
    expect(LeadAutomationLog::where('lead_id', $noEmail->id)->value('meta'))->toMatchArray(['skip_reason' => 'no_valid_email']);

    // valid lead -> sent, exactly once
    $good = LeadDetail::factory()->create(['email' => 'jo@example.com']);
    expect($svc->runWelcomeEmail($good))->toBe('sent');
    expect($svc->runWelcomeEmail($good->fresh()))->toBe('sent'); // second call: no-op
    Mail::assertSent(NewLeadWelcomeMail::class, 1);
    Mail::assertSent(NewLeadWelcomeMail::class, fn ($m) => $m->hasTo('jo@example.com'));
    expect(LeadAutomationLog::where('lead_id', $good->id)->count())->toBe(1)
        ->and(LeadAutomationLog::where('lead_id', $good->id)->value('status'))->toBe('sent');
});

test('a new lead triggers the queued welcome-email listener when the feature is on', function () {
    Mail::fake();
    config(['ai.welcome_email.enabled' => true]);

    $lead = LeadDetail::factory()->create(['email' => 'fresh@example.com']);

    Mail::assertSent(NewLeadWelcomeMail::class, fn ($m) => $m->hasTo('fresh@example.com'));
    expect(LeadAutomationLog::where('lead_id', $lead->id)->where('action_key', 'welcome_email')->value('status'))->toBe('sent');
});

test('the campaign allow-list gates the automation', function () {
    Mail::fake();
    config(['ai.welcome_email.enabled' => true, 'ai.welcome_email.only_campaign_ids' => [77]]);

    $off = LeadDetail::factory()->create(['email' => 'a@example.com', 'campaign_id' => 5]);
    expect(app(LeadAutomationService::class)->runWelcomeEmail($off))->toBe('skipped');

    $on = LeadDetail::factory()->create(['email' => 'b@example.com', 'campaign_id' => 77]);
    expect(app(LeadAutomationService::class)->runWelcomeEmail($on))->toBe('sent');
});

test('the welcome mailable renders', function () {
    expect((new NewLeadWelcomeMail('Subject', "Line one\nLine two"))->render())->toContain('Line one');
});

test('the automation dashboard is permission-gated and shows the rollup', function () {
    config(['ai.welcome_email.enabled' => true]);
    Mail::fake();
    LeadDetail::factory()->create(['email' => 'x@example.com']);
    LeadAutomationLog::create(['lead_id' => 999, 'action_key' => 'welcome_email', 'status' => 'failed', 'error_message' => 'boom']);

    Livewire::actingAs(User::factory()->withRole(['Customers'])->create())
        ->test(AutomationDashboard::class)->assertForbidden();

    Livewire::actingAs(User::factory()->withRole(['Settings'])->create())
        ->test(AutomationDashboard::class)
        ->assertOk()
        ->assertSee('welcome email')
        ->assertSee('boom');
});
