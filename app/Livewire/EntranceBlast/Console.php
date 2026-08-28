<?php

namespace App\Livewire\EntranceBlast;

use App\Models\BasicSettings;
use App\Services\Lead\EntranceBlastService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Entrance Blast configuration console. Ported from the legacy
 * EntranceBlastController (credential slots, message groups, delay / campaign
 * split). The blast execution itself is client-side against entrancegrp.com
 * and is out of scope here.
 */
class Console extends Component
{
    // slot form
    public int $slotUserId = 4055;

    public string $clientId = '';

    public string $accessToken = '';

    public string $refreshToken = '';

    // message-group form
    public string $grpName = '';

    public string $grpText = '';

    // settings
    public float $delayMinutes = 1.0;

    public int $campaignSplit = 1;

    public function mount(EntranceBlastService $service): void
    {
        abort_unless(auth()->user()?->hasPermission('Lead Blaster'), 403);

        $bs = BasicSettings::singleton();
        $this->delayMinutes = round(((int) $bs->entrance_delay) / 60000, 2) ?: 1.0;
        $this->campaignSplit = max(1, (int) ($bs->entrance_split ?? 1));
    }

    protected function service(): EntranceBlastService
    {
        return app(EntranceBlastService::class);
    }

    public function saveSlot(): void
    {
        $this->validate([
            'slotUserId' => ['required', Rule::in([4055, 4479, 4480])],
            'accessToken' => ['required', 'string', 'max:2000'],
        ]);

        $this->service()->saveSlot($this->slotUserId, [
            'clientId' => $this->clientId,
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
        ]);

        $this->reset(['clientId', 'accessToken', 'refreshToken']);
        Flux::toast(variant: 'success', text: __('Entrance slot :id saved.', ['id' => $this->slotUserId]));
    }

    public function clearSlot(int $userId): void
    {
        $this->service()->clearSlot($userId);
        Flux::toast(variant: 'success', text: __('Entrance slot :id cleared.', ['id' => $userId]));
    }

    public function createGroup(): void
    {
        $this->validate([
            'grpName' => ['required', 'string', 'max:255'],
            'grpText' => ['required', 'string'],
        ]);

        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $this->grpText)));
        abort_if($lines === [], 422);

        $this->service()->createMessageGroup($this->grpName, array_values($lines));
        $this->reset(['grpName', 'grpText']);
        Flux::toast(variant: 'success', text: __('Message group created.'));
    }

    public function deleteGroup(string $uniqueGrpId): void
    {
        $this->service()->deleteMessageGroup($uniqueGrpId);
        Flux::toast(variant: 'success', text: __('Message group deleted.'));
    }

    public function attachGroup(int $userId, string $uniqueGrpId): void
    {
        $this->service()->attachMessageGroup($userId, $uniqueGrpId);
        Flux::toast(variant: 'success', text: __('Group attached to slot :id.', ['id' => $userId]));
    }

    public function saveSettings(): void
    {
        $this->validate([
            'delayMinutes' => ['required', 'numeric', 'min:0.1'],
            'campaignSplit' => ['required', 'integer', 'min:1'],
        ]);

        $this->service()->setDelayMinutes((float) $this->delayMinutes);
        $this->service()->setCampaignSplit((int) $this->campaignSplit);
        Flux::toast(variant: 'success', text: __('Blast settings saved.'));
    }

    public function render(): View
    {
        return view('livewire.entrance-blast.console', [
            'slots' => $this->service()->slots(),
            'groups' => $this->service()->messageGroups(),
        ])->layout('layouts.app', ['title' => 'Entrance Blast']);
    }
}
