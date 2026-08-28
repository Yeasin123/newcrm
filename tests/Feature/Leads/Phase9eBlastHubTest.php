<?php

use App\Livewire\BlastHub\Index;
use App\Models\DncNumber;
use App\Models\LeadAiScore;
use App\Models\LeadBlastItem;
use App\Models\LeadBlastRun;
use App\Models\LeadDetail;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\Lead\LeadBlasterService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function blaster(): LeadBlasterService
{
    return app(LeadBlasterService::class);
}

function coldLead(array $attrs = []): LeadDetail
{
    return LeadDetail::factory()->create(array_merge([
        'status' => 0, 'contact_text' => 'Cell Phone', 'phone_company' => 'Verizon',
        'is_download' => false,
    ], $attrs));
}

beforeEach(function () {
    LeadStatus::create(['title' => 'New']);
    LeadStatus::create(['title' => 'Interested']);
});

test('a cold run selects only un-statused cell-phone leads and counts the exclusions', function () {
    $a = coldLead(['contact' => '5550001111']);
    coldLead(['contact' => '5550002222', 'contact_text' => 'Landline']);          // non-cell
    coldLead(['contact' => '5550003333', 'status' => LeadStatus::where('title', 'Interested')->value('id')]); // not cold
    $d = coldLead(['contact' => '5550004444', 'phone_company' => 'T-Mobile']);

    $run = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'all', 'lead_count' => '50000',
        'min_ai_score' => 'any', 'dnc_excluded' => false,
    ]);

    expect($run->selected_count)->toBe(2)
        ->and($run->excluded_non_cell_count)->toBeGreaterThanOrEqual(1)
        ->and(LeadBlastItem::where('lead_blast_run_id', $run->id)->pluck('lead_id')->sort()->values()->all())
        ->toBe(collect([$a->id, $d->id])->sort()->values()->all());

    $excludeTmobile = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'exclude_tmobile', 'lead_count' => '50000',
        'min_ai_score' => 'any', 'dnc_excluded' => false,
    ]);
    expect($excludeTmobile->selected_count)->toBe(1);
});

test('the min AI score filter keeps only leads scored at or above the threshold', function () {
    $hi = coldLead(['contact' => '5551110000']);
    $lo = coldLead(['contact' => '5551110001']);
    LeadAiScore::create(['lead_id' => $hi->id, 'score' => 60]);
    LeadAiScore::create(['lead_id' => $lo->id, 'score' => 10]);

    $run = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'all', 'lead_count' => '50000',
        'min_ai_score' => '50', 'dnc_excluded' => false,
    ]);

    expect($run->selected_count)->toBe(1)
        ->and(LeadBlastItem::where('lead_blast_run_id', $run->id)->value('lead_id'))->toBe($hi->id)
        ->and((int) $run->min_ai_score)->toBe(50);
});

test('downloading a run locks its leads out of the next run for 28 days', function () {
    $lead = coldLead(['contact' => '5552220000']);

    $first = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'all', 'lead_count' => '50000',
        'min_ai_score' => 'any', 'dnc_excluded' => false,
    ]);
    expect($first->selected_count)->toBe(1);

    blaster()->lockRunItems($first);

    $lead->refresh();
    expect((int) $lead->is_download)->toBe(1)
        ->and($lead->lock_lead)->toBe('lock')
        ->and($lead->lock_time)->not->toBeNull()
        ->and($first->fresh()->status)->toBe('downloaded');

    $item = LeadBlastItem::where('lead_blast_run_id', $first->id)->firstOrFail();
    expect(Carbon::parse($item->locked_until)->isAfter(now()->addDays(27)))->toBeTrue();

    $second = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'all', 'lead_count' => '50000',
        'min_ai_score' => 'any', 'dnc_excluded' => false,
    ]);
    expect($second->selected_count)->toBe(0)
        ->and($second->excluded_locked_count)->toBeGreaterThanOrEqual(1);
});

test('DNC exclusion drops leads whose normalised number is on the list', function () {
    coldLead(['contact' => '(555) 123-4567']);
    coldLead(['contact' => '5559999999']);
    DncNumber::create(['phone_normalized' => '5551234567']);

    $run = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'all', 'lead_count' => '50000',
        'min_ai_score' => 'any', 'dnc_excluded' => true,
    ]);

    expect($run->selected_count)->toBe(1)
        ->and($run->excluded_dnc_count)->toBe(1)
        ->and($run->dnc_excluded)->toBeTrue();
});

test('createCsv writes a header row and one line per selected lead', function () {
    Storage::fake('local');
    coldLead(['contact' => '5553330000', 'first_name' => 'Csv', 'last_name' => 'Tester', 'email' => 'csv@example.com']);

    $run = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'all', 'lead_count' => '50000',
        'min_ai_score' => 'any', 'dnc_excluded' => false,
    ]);

    $path = blaster()->createCsv($run);

    Storage::disk('local')->assertExists($path);
    $csv = Storage::disk('local')->get($path);
    expect($csv)->toContain('first_name,last_name,phone,email,carrier,campaign,status,ai_score')
        ->and($csv)->toContain('Csv,Tester,5553330000,csv@example.com');
    expect($run->fresh()->csv_path)->toBe($path);
});

test('the Blast Hub screen generates a run and is gated by the Lead Blaster permission', function () {
    coldLead(['contact' => '5554440000']);

    Livewire::actingAs(User::factory()->withRole(['Customers'])->create())
        ->test(Index::class)->assertForbidden();

    Livewire::actingAs(User::factory()->withRole(['Lead Blaster'])->create())
        ->test(Index::class)
        ->set('blastType', 'cold')
        ->set('carrierFilter', 'all')
        ->call('generate')
        ->assertHasNoErrors();

    expect(LeadBlastRun::count())->toBe(1)
        ->and(LeadBlastRun::first()->selected_count)->toBe(1);
});

test('the download route builds the csv, locks the run and streams a file', function () {
    Storage::fake('local');
    $lead = coldLead(['contact' => '5555550000']);

    $run = blaster()->generateRun([
        'blast_type' => 'cold', 'carrier_filter' => 'all', 'lead_count' => '50000',
        'min_ai_score' => 'any', 'dnc_excluded' => false,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('blast-hub.download', $run))
        ->assertOk()
        ->assertDownload('lead-blaster-run-'.$run->id.'-'.now()->format('Ymd_His').'.csv');

    expect($run->fresh()->status)->toBe('downloaded')
        ->and((int) $lead->fresh()->is_download)->toBe(1);
});
