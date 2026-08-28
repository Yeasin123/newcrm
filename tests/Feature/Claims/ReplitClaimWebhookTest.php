<?php

use App\Models\Claim;
use App\Models\ClaimWebhookLog;

test('a valid payload updates the claim and logs the event', function () {
    $claim = Claim::factory()->create(['claim_no' => 'WHK-1']);

    $payload = [
        'event' => 'status_changed',
        'claim' => ['claim_number' => 'WHK-1'],
        'status' => ['workflow_step' => 'dispatch', 'status' => 'in_progress', 'sub_status' => 'tech_enroute'],
    ];

    $this->postJson('/replit/claim/status', $payload)
        ->assertOk()
        ->assertJson(['success' => true, 'claim_no' => 'WHK-1']);

    $claim->refresh();
    expect($claim->replit_status)->toBe('in_progress')
        ->and($claim->replit_workflow_step)->toBe('dispatch')
        ->and($claim->replit_payload_received_at)->not->toBeNull()
        ->and(ClaimWebhookLog::where('claim_id', $claim->id)->where('matched_claim', true)->exists())->toBeTrue();
});

test('a missing claim number returns 422', function () {
    $this->postJson('/replit/claim/status', ['event' => 'x', 'status' => []])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect(ClaimWebhookLog::where('note', 'claim number missing')->exists())->toBeTrue();
});

test('an unknown claim number returns 404 and is still logged', function () {
    $this->postJson('/replit/claim/status', ['claim_no' => 'NOPE-999'])
        ->assertStatus(404)
        ->assertJson(['success' => false, 'claim_no' => 'NOPE-999']);

    expect(ClaimWebhookLog::where('claim_no', 'NOPE-999')->where('matched_claim', false)->exists())->toBeTrue();
});

test('the webhook route is exempt from CSRF', function () {
    // A plain POST (no token) must not be rejected with 419.
    $this->post('/replit/claim/status', ['claim_no' => 'X'])->assertStatus(404);
});
