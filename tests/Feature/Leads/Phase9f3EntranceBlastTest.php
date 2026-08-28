<?php

use App\Livewire\EntranceBlast\Console;
use App\Models\BasicSettings;
use App\Models\EntranceAuth;
use App\Models\EntranceGrpMessage;
use App\Models\User;
use App\Services\Lead\EntranceBlastService;
use Livewire\Livewire;

function blastService(): EntranceBlastService
{
    return app(EntranceBlastService::class);
}

test('saving and clearing an entrance slot updates the singleton auth row', function () {
    blastService()->saveSlot(4055, ['clientId' => 'C1', 'accessToken' => 'A1', 'refreshToken' => 'R1']);

    $auth = EntranceAuth::first();
    $blob = json_decode($auth->entrance_one_data, true);
    expect((int) $blob['userId'])->toBe(4055)
        ->and($blob['accessToken'])->toBe('A1')
        ->and(json_decode($auth->entrance_userIds, true))->toContain(['userId' => 4055]);

    blastService()->saveSlot(4055, ['clientId' => 'C1', 'accessToken' => 'A2', 'refreshToken' => 'R1']);
    expect(EntranceAuth::count())->toBe(1); // still one singleton row

    blastService()->clearSlot(4055);
    expect(EntranceAuth::first()->entrance_one_data)->toBeNull();
});

test('a message group is a set of rows sharing one unique id', function () {
    $id = blastService()->createMessageGroup('Welcome set', ['Hi there', 'Second touch', '   ']);

    expect(EntranceGrpMessage::where('unique_grp_id', $id)->count())->toBe(2);

    $groups = blastService()->messageGroups();
    expect($groups)->toHaveCount(1)
        ->and($groups->first()['grp_name'])->toBe('Welcome set')
        ->and($groups->first()['lines'])->toBe(['Second touch', 'Hi there']); // legacy orders id desc

    blastService()->attachMessageGroup(4479, $id);
    expect(json_decode(EntranceAuth::first()->message_two, true))->toHaveCount(2);

    expect(blastService()->deleteMessageGroup($id))->toBe(2)
        ->and(EntranceGrpMessage::count())->toBe(0);
});

test('delay is stored in milliseconds and split as an integer', function () {
    blastService()->setDelayMinutes(2.5);
    blastService()->setCampaignSplit(4);

    $bs = BasicSettings::first();
    expect((int) $bs->entrance_delay)->toBe(150000)
        ->and((int) $bs->entrance_split)->toBe(4);
});

test('the Entrance Blast console is permission-gated and its actions persist', function () {
    Livewire::actingAs(User::factory()->withRole(['Customers'])->create())
        ->test(Console::class)->assertForbidden();

    Livewire::actingAs(User::factory()->withRole(['Lead Blaster'])->create())
        ->test(Console::class)
        ->set('slotUserId', 4480)->set('accessToken', 'TOK')->call('saveSlot')
        ->set('grpName', 'G')->set('grpText', "one\ntwo")->call('createGroup')
        ->set('delayMinutes', 1.5)->set('campaignSplit', 2)->call('saveSettings')
        ->assertHasNoErrors();

    expect(EntranceAuth::first()->entrance_three_data)->not->toBeNull()
        ->and(EntranceGrpMessage::count())->toBe(2)
        ->and((int) BasicSettings::first()->entrance_delay)->toBe(90000);
});
