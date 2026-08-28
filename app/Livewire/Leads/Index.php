<?php

namespace App\Livewire\Leads;

use App\Models\CampaignFile;
use App\Services\Leads\CampaignStatsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Leads & Campaigns — the campaign board. One card per `campaign_files` row
 * with its lead roll-up (CampaignStatsService, cached), CRM-wide totals in
 * the header, and a "New Campaign" quick-create. Drilling into a card opens
 * the per-campaign lead list (Leads\Campaign).
 */
class Index extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'active'; // active | all | inactive

    // New Campaign modal
    public bool $showNew = false;

    public string $newName = '';

    public string $newSubtitle = '';

    public string $newType = 'website';

    public function setFilter(string $f): void
    {
        $this->filter = in_array($f, ['active', 'all', 'inactive'], true) ? $f : 'active';
    }

    public function updateLeads(CampaignStatsService $stats): void
    {
        $stats->flush();
        Flux::toast(variant: 'success', text: __('Lead counts refreshed.'));
    }

    public function createCampaign(): void
    {
        $data = $this->validate([
            'newName' => 'required|string|max:190',
            'newSubtitle' => 'nullable|string|max:190',
            'newType' => 'required|string|max:60',
        ]);

        $campaign = CampaignFile::create([
            'name' => $data['newName'],
            'user_id' => (string) auth()->id(),
            'status' => 1,
            'price_type' => $data['newType'],
        ]);

        $this->reset('showNew', 'newName', 'newSubtitle', 'newType');
        Flux::toast(variant: 'success', text: __('Campaign created.'));

        $this->redirectRoute('leads.campaign', ['campaign' => $campaign->id], navigate: true);
    }

    public function render(CampaignStatsService $stats): View
    {
        $per = $stats->perCampaign();

        $campaigns = CampaignFile::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filter === 'active', fn ($q) => $q->where('status', 1))
            ->when($this->filter === 'inactive', fn ($q) => $q->where('status', '!=', 1))
            ->orderByDesc('id')
            ->get(['id', 'name', 'status', 'price_type', 'created_at'])
            ->map(function ($c) use ($per) {
                $c->stats = $per[$c->id] ?? ['total' => 0, 'sale' => 0, 'call_back' => 0, 'no_answer' => 0, 'no_status' => 0, 'conv' => 0];

                return $c;
            });

        return view('livewire.leads.index', [
            'campaigns' => $campaigns,
            'kpi' => $stats->global(),
        ])->layout('layouts.app', ['title' => 'Leads & Campaigns']);
    }
}
