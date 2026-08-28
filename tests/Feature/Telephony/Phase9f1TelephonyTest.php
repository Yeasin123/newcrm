<?php

use App\Models\Entrance;
use App\Models\LeadDetail;
use App\Models\SmsText;
use App\Models\Webhook;
use App\Services\Telephony\JdiVoicemailGateway;
use App\Services\Telephony\NullSmsGateway;
use App\Services\Telephony\NullVoiceGateway;
use App\Services\Telephony\NullVoicemailGateway;
use App\Services\Telephony\SmsGateway;
use App\Services\Telephony\TelnyxSmsGateway;
use App\Services\Telephony\TwilioVoiceGateway;
use App\Services\Telephony\VoiceGateway;
use App\Services\Telephony\VoicemailGateway;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

test('the telephony gateways are Null-bound until their flags are set', function () {
    config(['telephony.sms_enabled' => false, 'telephony.voice_enabled' => false, 'telephony.voicemail_enabled' => false]);

    expect(app(SmsGateway::class))->toBeInstanceOf(NullSmsGateway::class)
        ->and(app(VoiceGateway::class))->toBeInstanceOf(NullVoiceGateway::class)
        ->and(app(VoicemailGateway::class))->toBeInstanceOf(NullVoicemailGateway::class);

    config(['comms.enabled' => true, 'comms.sms' => true, 'comms.voice' => true, 'comms.voicemail' => true, 'telephony.sms_enabled' => true, 'telephony.voice_enabled' => true, 'telephony.voicemail_enabled' => true]);

    expect(app(SmsGateway::class))->toBeInstanceOf(TelnyxSmsGateway::class)
        ->and(app(VoiceGateway::class))->toBeInstanceOf(TwilioVoiceGateway::class)
        ->and(app(VoicemailGateway::class))->toBeInstanceOf(JdiVoicemailGateway::class);
});

test('the null SMS gateway sends nothing and reports it is disabled', function () {
    $result = app(NullSmsGateway::class)->send('5551234567', 'hi');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('disabled');
    Http::assertNothingSent();
});

test('phone normalisation and local variants match the legacy TelnyxMessageService', function () {
    $g = app(NullSmsGateway::class);

    expect($g->normalizePhone('(555) 123-4567'))->toBe('+15551234567')
        ->and($g->normalizePhone('15551234567'))->toBe('+15551234567')
        ->and($g->normalizePhone('  '))->toBeNull()
        ->and($g->localPhoneVariants('+1 (555) 123-4567'))
        ->toBe(['+1 (555) 123-4567', '15551234567', '5551234567', '+15551234567']);
});

test('the live Telnyx SMS gateway posts to the v2 messages endpoint', function () {
    config([
        'comms.enabled' => true, 'comms.sms' => true,
        'telephony.sms_enabled' => true,
        'services.telnyx.api_key' => 'KEY', 'services.telnyx.from_number' => '5550000000',
    ]);
    Http::fake(['*api.telnyx.com/v2/messages' => Http::response(['data' => ['id' => 'msg-1']], 200)]);

    $result = app(SmsGateway::class)->send('5551234567', 'Your payment failed');

    expect($result['success'])->toBeTrue()->and($result['message_id'])->toBe('msg-1');
    Http::assertSent(fn ($req) => $req['to'] === '+15551234567' && $req['from'] === '+15550000000');
});

test('the ClickSend inbound webhook records a CS entrance reply', function () {
    SmsText::create(['sms_word' => 'yes']);
    $lead = LeadDetail::factory()->create(['contact' => '5559990000']);

    $this->postJson('/clicksend/get/notification', [
        'sms' => '+15559990000', 'message' => 'yes', 'message_id' => 'CS-ABC-123',
    ])->assertOk();

    $entrance = Entrance::where('lead_detail_id', $lead->id)->firstOrFail();
    expect($entrance->webhook_name)->toBe('Cs')
        ->and($entrance->contact)->toBe('5559990000')
        ->and($entrance->message_id)->toBe('CS-ABC-123');
    expect(Webhook::where('lead_id', $lead->id)->where('CS', 'CS')->count())->toBe(1);
});
