<?php

use App\Events\LeadCreated;
use App\Livewire\Leads\Campaign;
use App\Livewire\Leads\Index;
use App\Livewire\Leads\Show;
use App\Models\CampaignFile;
use App\Models\ConvertedLead;
use App\Models\LeadAiScore;
use App\Models\LeadDetail;
use App\Models\LeadStatus;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('creating a lead fires the LeadCreated event once', function () {
    Event::fake([LeadCreated::class]);

    LeadDetail::factory()->create();

    Event::assertDispatchedTimes(LeadCreated::class, 1);
});

test('the campaigns board lists campaigns with a lead roll-up and filters by name', function () {
    $ahw = CampaignFile::create(['name' => 'American Home Warranty', 'status' => 1]);
    $blast = CampaignFile::create(['name' => 'NV JIM Blast', 'status' => 1]);
    LeadDetail::factory()->count(3)->create(['campaign_id' => $ahw->id, 'status' => 1]);   // Sale
    LeadDetail::factory()->count(2)->create(['campaign_id' => $ahw->id, 'status' => 0]);   // No Status

    Livewire::test(Index::class)
        ->assertSee('American Home Warranty')
        ->assertSee('NV JIM Blast')
        ->assertSee('3 Sale')
        ->assertSee('60% conv.')
        ->set('search', 'Warranty')
        ->assertSee('American Home Warranty')
        ->assertDontSee('NV JIM Blast');
});

test('a campaign page shows only its own leads and filters by tag', function () {
    $mine = CampaignFile::create(['name' => 'Mine', 'status' => 1]);
    $other = CampaignFile::create(['name' => 'Other', 'status' => 1]);
    LeadDetail::factory()->create(['first_name' => 'Inq', 'last_name' => 'Lead', 'campaign_id' => $mine->id, 'lead_tag' => 'Inquiry']);
    LeadDetail::factory()->create(['first_name' => 'Cold', 'last_name' => 'Lead', 'campaign_id' => $mine->id, 'lead_tag' => null]);
    LeadDetail::factory()->create(['first_name' => 'Elsewhere', 'last_name' => 'Lead', 'campaign_id' => $other->id]);

    Livewire::test(Campaign::class, ['campaign' => $mine])
        ->assertSee('Inq Lead')
        ->assertSee('Cold Lead')
        ->assertDontSee('Elsewhere Lead')
        ->call('setTag', 'inquiry')
        ->assertSee('Inq Lead')
        ->assertDontSee('Cold Lead');
});

test('editing a campaign from its page updates name and status', function () {
    $campaign = CampaignFile::create(['name' => 'Old Name', 'status' => 1]);

    Livewire::test(Campaign::class, ['campaign' => $campaign])
        ->call('openEdit')
        ->set('ceName', 'New Name')
        ->set('ceStatus', '0')
        ->call('saveCampaign')
        ->assertHasNoErrors()
        ->assertSet('showEdit', false);

    $campaign->refresh();
    expect($campaign->name)->toBe('New Name')->and((int) $campaign->status)->toBe(0);
});

test('importing a CSV creates campaign-scoped leads', function () {
    $campaign = CampaignFile::create(['name' => 'CSV Camp', 'status' => 1]);
    $csv = "first_name,last_name,contact,email,city\nRhonda,Katz,5551112222,rhonda@example.com,Reno\n,,,,Orphantown\n";
    $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

    Livewire::test(Campaign::class, ['campaign' => $campaign])
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors()
        ->assertSet('imported', 1)
        ->assertSet('skipped', 1);

    $lead = LeadDetail::where('campaign_id', $campaign->id)->first();
    expect($lead->first_name)->toBe('Rhonda')->and($lead->city)->toBe('Reno');
});

test('AI Scores fills unscored leads with a status heuristic', function () {
    $campaign = CampaignFile::create(['name' => 'Score Camp', 'status' => 1]);
    $sale = LeadDetail::factory()->create(['campaign_id' => $campaign->id, 'status' => 1]);
    $none = LeadDetail::factory()->create(['campaign_id' => $campaign->id, 'status' => 0]);

    Livewire::test(Campaign::class, ['campaign' => $campaign])->call('generateScores');

    expect(LeadAiScore::where('lead_id', $sale->id)->value('score'))->toBe(92)
        ->and(LeadAiScore::where('lead_id', $none->id)->value('score'))->toBe(20);
});

test('the campaign CSV download route streams a csv', function () {
    $campaign = CampaignFile::create(['name' => 'Dl Camp', 'status' => 1]);
    LeadDetail::factory()->create(['campaign_id' => $campaign->id, 'first_name' => 'Downloadable']);

    $res = $this->get(route('leads.campaign.export', $campaign));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
});

test('adding a lead from a campaign attaches it to that campaign and fires LeadCreated', function () {
    Event::fake([LeadCreated::class]);
    $campaign = CampaignFile::create(['name' => 'Intake', 'status' => 1]);

    Livewire::test(Campaign::class, ['campaign' => $campaign])
        ->set('lead.first_name', 'Walt')
        ->set('lead.contact', '5551234567')
        ->call('addLead')
        ->assertHasNoErrors()
        ->assertSet('showAdd', false);

    $lead = LeadDetail::where('campaign_id', $campaign->id)->first();
    expect($lead)->not->toBeNull()->and($lead->first_name)->toBe('Walt');
    Event::assertDispatchedTimes(LeadCreated::class, 1);
});

test('a note can be added to a lead', function () {
    $lead = LeadDetail::factory()->create();

    Livewire::test(Show::class, ['lead' => $lead])
        ->set('noteBody', 'Left a voicemail.')
        ->call('addNote')
        ->assertHasNoErrors();

    expect(Note::where('lead_id', $lead->id)->value('notes'))->toBe('Left a voicemail.');
});

test('a reminder can be scheduled', function () {
    $lead = LeadDetail::factory()->create();

    Livewire::test(Show::class, ['lead' => $lead])
        ->set('reminderDate', now()->addDay()->toDateString())
        ->set('reminderTime', '14:30')
        ->call('addReminder')
        ->assertHasNoErrors();

    expect(Reminder::where('lead_id', $lead->id)->count())->toBe(1);
});

test('status and assignment can be updated', function () {
    $status = LeadStatus::create(['title' => 'Interested']);
    $rep = User::factory()->create();
    $lead = LeadDetail::factory()->create();

    Livewire::test(Show::class, ['lead' => $lead])
        ->set('statusId', (string) $status->id)
        ->call('updateStatus')
        ->set('assignRepId', (string) $rep->id)
        ->call('assignRep');

    $lead->refresh();
    expect((int) $lead->status)->toBe($status->id)
        ->and($lead->user_id)->toBe((string) $rep->id);
});

test('sending a lead to the sale sheet creates a converted lead once', function () {
    $lead = LeadDetail::factory()->create(['first_name' => 'Converted', 'last_name' => 'Person']);

    $component = Livewire::test(Show::class, ['lead' => $lead]);
    $component->call('sendToSaleSheet');
    $component->call('sendToSaleSheet'); // second call must be a no-op

    expect(ConvertedLead::where('lead_id', $lead->id)->count())->toBe(1);
});

test('the Main Page permission gates the leads list', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('leads.index'))->assertForbidden();
});
