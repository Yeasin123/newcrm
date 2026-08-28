<?php

use App\Livewire\Reporting\WebhookReport;
use App\Models\Client;
use App\Models\Entrance;
use App\Models\LeadDetail;
use App\Models\SmsText;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Reporting\EntranceReportService;
use App\Services\Reporting\WebhookReportService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('the daily counts roll up each source column per day', function () {
    $lead = LeadDetail::factory()->create();
    $today = Carbon::today();
    $yesterday = Carbon::yesterday();

    Webhook::create(['lead_id' => $lead->id, 'STORE_DATE' => $today, 'EN' => 'EN']);
    Webhook::create(['lead_id' => $lead->id, 'STORE_DATE' => $today, 'EN' => 'EN']);
    Webhook::create(['lead_id' => $lead->id, 'STORE_DATE' => $today, 'CS' => 'CS']);
    Webhook::create(['lead_id' => $lead->id, 'STORE_DATE' => $yesterday, 'MM' => 'MM']);

    $rows = app(WebhookReportService::class)->dailyCounts();

    expect($rows)->toHaveCount(2)
        ->and($rows->first()->date)->toBe($today->toDateString()) // ordered desc
        ->and((int) $rows->first()->EN_count)->toBe(2)
        ->and((int) $rows->first()->CS_count)->toBe(1)
        ->and((int) $rows->first()->total)->toBe(3)
        ->and((int) $rows->last()->MM_count)->toBe(1);
});

test('the detail query returns the rows behind one day and source, and rejects an unknown column', function () {
    $lead = LeadDetail::factory()->create(['first_name' => 'Webby']);
    Webhook::create(['lead_id' => $lead->id, 'STORE_DATE' => Carbon::today(), 'EN' => 'EN']);
    Webhook::create(['lead_id' => $lead->id, 'STORE_DATE' => Carbon::today(), 'CS' => 'CS']);

    $rows = app(WebhookReportService::class)->detail(Carbon::today()->toDateString(), 'EN');
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->leadDetail->first_name)->toBe('Webby');

    expect(fn () => app(WebhookReportService::class)->detail(Carbon::today()->toDateString(), 'DROP'))
        ->toThrow(NotFoundHttpException::class);
});

test('the entrance report attributes yes replies, sales and revenue per channel', function () {
    SmsText::create(['sms_word' => 'yes']);
    $day = Carbon::today();

    // a lead that replied "yes" on the "one" entrance and became a sale, with a matching client
    $saleLead = LeadDetail::factory()->create(['status' => 1, 'email' => 'sold@example.com', 'contact' => '5551230000']);
    Client::factory()->create([
        'email' => 'sold@example.com', 'contact' => '5551230000',
        'price_cal_response' => json_encode(['price' => '299.00', 'discount_price' => '0']),
    ]);
    Entrance::create(['account_key' => 'ENTRANCE8HEVSK9WKA4055', 'lead_detail_id' => $saleLead->id, 'message' => 'yes', 'created_at' => $day, 'updated_at' => $day]);

    // a lead that replied "yes" on the same entrance but is still an inquiry
    $inqLead = LeadDetail::factory()->create(['status' => 0, 'lead_tag' => 'Inquiry', 'contact' => '5559990000']);
    Entrance::create(['account_key' => 'ENTRANCE8HEVSK9WKA4055', 'lead_detail_id' => $inqLead->id, 'message' => 'yes', 'created_at' => $day, 'updated_at' => $day]);

    $rows = app(EntranceReportService::class)->dailyRows();
    $today = $rows->firstWhere('day', $day->toDateString());

    expect($today)->not->toBeNull()
        ->and($today['total_yes'])->toBe(2)
        ->and($today['channels']['one']['yes'])->toBe(2)
        ->and($today['channels']['one']['sale'])->toBe(1)
        ->and($today['channels']['one']['inquiry'])->toBe(1)
        ->and($today['channels']['one']['sale_price'])->toBe(299.0)
        ->and($today['channels']['mm']['yes'])->toBe(0);
});

test('the webhook report screen is permission-gated and drills into a cell', function () {
    $lead = LeadDetail::factory()->create();
    Webhook::create(['lead_id' => $lead->id, 'STORE_DATE' => Carbon::today(), 'QUOTE' => 'QUOTE']);

    Livewire::actingAs(User::factory()->withRole(['Customers'])->create())
        ->test(WebhookReport::class)->assertForbidden();

    Livewire::actingAs(User::factory()->withRole(['Settings'])->create())
        ->test(WebhookReport::class)
        ->assertOk()
        ->assertSee('QUOTE')
        ->call('showDetail', Carbon::today()->toDateString(), 'QUOTE')
        ->assertSet('detailType', 'QUOTE')
        ->assertSeeHtml('QUOTE — '.Carbon::today()->toDateString())
        ->call('closeDetail')
        ->assertSet('detailDate', null);
});
