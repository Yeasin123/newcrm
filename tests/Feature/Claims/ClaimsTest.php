<?php

use App\Enums\ClaimStatus;
use App\Livewire\Claims\Index;
use App\Livewire\Claims\Show;
use App\Models\Claim;
use App\Models\ClaimClosedDetail;
use App\Models\ClaimTrack;
use App\Models\Part;
use App\Models\Technician;
use App\Models\User;
use App\Services\Claim\ClaimService;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('the claims list filters by status and technician', function () {
    $tech = Technician::factory()->create(['first_name' => 'Fixer']);
    Claim::factory()->create(['claim_no' => 'OPEN-1', 'status' => 0]);
    Claim::factory()->create(['claim_no' => 'CLOSED-1', 'status' => ClaimStatus::Closed->value, 'tech_id' => $tech->id]);

    Livewire::test(Index::class)
        ->set('status', (string) ClaimStatus::Closed->value)
        ->assertSee('CLOSED-1')
        ->assertDontSee('OPEN-1')
        ->set('status', '')
        ->set('technician', (string) $tech->id)
        ->assertSee('CLOSED-1')
        ->assertDontSee('OPEN-1');
});

test('assigning a technician writes a track row', function () {
    $claim = Claim::factory()->create();
    $tech = Technician::factory()->create();

    Livewire::test(Show::class, ['claim' => $claim])
        ->set('techId', (string) $tech->id)
        ->set('techSchedule', 'Mon AM')
        ->call('assignTechnician');

    $claim->refresh();
    expect($claim->tech_id)->toBe($tech->id)
        ->and($claim->tech_schedule)->toBe('Mon AM')
        ->and(ClaimTrack::where('claim_id', $claim->id)->where('button_text', 'Technician assigned')->exists())->toBeTrue();
});

test('a part can be added to a claim', function () {
    $claim = Claim::factory()->create();

    Livewire::test(Show::class, ['claim' => $claim])
        ->set('partName', 'Compressor')
        ->set('partPrice', '250.50')
        ->call('addPart')
        ->assertHasNoErrors();

    expect(Part::where('claim_id', $claim->id)->first())
        ->name->toBe('Compressor')
        ->price->toBe(250.5);
});

test('closing a claim records the summary and moves it to Closed', function () {
    $claim = Claim::factory()->create(['status' => 0]);

    app(ClaimService::class)->close($claim, [
        'dispatch_amount' => 60,
        'claim_spent_price' => 180.0,
        'notes' => 'Replaced compressor',
    ]);

    $claim->refresh();
    expect($claim->status)->toBe(ClaimStatus::Closed->value)
        ->and((float) $claim->total_price)->toBe(180.0)
        ->and(ClaimClosedDetail::where('claim_id', $claim->id)->exists())->toBeTrue();
});

test('the Claims permission gates the page', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('claims.index'))->assertForbidden();
});

test('the claims CSV export streams the filtered set', function () {
    Claim::factory()->create(['claim_no' => 'EXP-1', 'status' => 0]);

    $response = $this->get(route('claims.export'));

    $response->assertOk();
    expect($response->streamedContent())->toContain('claim_no')->toContain('EXP-1');
});
