<?php

namespace App\Livewire\BlastHub;

use App\Models\CampaignFile;
use App\Models\LeadBlastRun;
use App\Services\Lead\LeadBlasterService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Blast Hub console. Ported from the legacy LeadBlasterController@index/generate
 * — same filter set, same LeadBlasterService::generateRun call, same run
 * summary. The CSV download (which also locks the selected leads for 28 days)
 * is a plain GET route so the file streams outside Livewire.
 */
class Index extends Component
{
    public string $blastType = 'cold';

    public string $carrierFilter = 'all';

    public string $leadCount = '50000';

    public string $minAiScore = 'any';

    public ?int $campaignId = null;

    public bool $dncExcluded = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('Lead Blaster'), 403);
    }

    public function generate(LeadBlasterService $service): void
    {
        $validated = $this->validate([
            'blastType' => ['required', Rule::in(['cold', 'warm', 'hot'])],
            'carrierFilter' => ['required', Rule::in(['all', 'exclude_tmobile', 'tmobile_only'])],
            'leadCount' => ['required', Rule::in(['50000', '100000', '200000', '500000', 'all'])],
            'minAiScore' => ['required', Rule::in(['any', '25', '50', '75'])],
            'campaignId' => ['nullable', 'integer', 'exists:campaign_files,id'],
            'dncExcluded' => ['boolean'],
        ]);

        $run = $service->generateRun([
            'blast_type' => $validated['blastType'],
            'carrier_filter' => $validated['carrierFilter'],
            'lead_count' => $validated['leadCount'],
            'min_ai_score' => $validated['minAiScore'],
            'campaign_id' => $validated['campaignId'],
            'dnc_excluded' => $validated['dncExcluded'],
        ]);

        Flux::toast(variant: 'success', text: __('Generated :n lead(s).', ['n' => $run->selected_count]));
    }

    public function render(): View
    {
        $latestRun = LeadBlastRun::with([
            'campaign',
            'items' => fn ($q) => $q->with(['lead:id,first_name,last_name,contact,email,campaign_id,status'])
                ->latest('id')->limit(25),
        ])->latest('id')->first();

        return view('livewire.blast-hub.index', [
            'campaigns' => CampaignFile::query()->select('id', 'name')->orderBy('name')->get(),
            'latestRun' => $latestRun,
            'runs' => LeadBlastRun::with('campaign')->latest('id')->limit(10)->get(),
        ])->layout('layouts.app', ['title' => 'Blast Hub']);
    }
}
