<?php

namespace App\Livewire\Leads;

use App\Models\CampaignFile;
use App\Models\LeadAiScore;
use App\Models\LeadDetail;
use App\Models\LeadStatus;
use App\Services\Leads\CampaignStatsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A single campaign's lead board: its KPI roll-up, the CSV intake webhook,
 * tag (Inquiry / SMS / No-tag) + status + text filters, an infinite-scroll
 * lead list, and the campaign header actions — Edit, Import CSV, Download,
 * AI Scores, Mark All Inquiry, Add Lead. Row-level "Convert" hands off to
 * the Sale Sheet pre-filled from the lead.
 */
class Campaign extends Component
{
    use WithFileUploads;

    private const PAGE = 40;

    private const MAX = 800;

    /** How many unscored leads one "AI Scores" run seeds (fallback heuristic). */
    private const SCORE_BATCH = 2000;

    public CampaignFile $campaign;

    #[Url]
    public string $tag = ''; // '' | inquiry | sms | none

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public int $perPage = self::PAGE;

    // Add Lead modal
    public bool $showAdd = false;

    /** @var array<string,string> */
    public array $lead = [
        'first_name' => '', 'last_name' => '', 'email' => '', 'contact' => '',
        'address' => '', 'city' => '', 'state' => '', 'zip_code' => '',
    ];

    // Edit Campaign modal
    public bool $showEdit = false;

    public string $ceName = '';

    public string $ceType = '';

    public string $ceStatus = '1';

    // Import CSV modal
    public bool $showImport = false;

    public $importFile;

    public ?int $imported = null;

    public ?int $skipped = null;

    public ?string $importError = null;

    /** @var list<string> */
    public array $importPreview = [];

    // AI Scores modal
    public bool $showScores = false;

    public function mount(CampaignFile $campaign): void
    {
        $this->campaign = $campaign;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tag', 'status', 'search'], true)) {
            $this->perPage = self::PAGE;
        }
    }

    public function setTag(string $tag): void
    {
        $this->tag = in_array($tag, ['', 'inquiry', 'sms', 'none'], true) ? $tag : '';
        $this->perPage = self::PAGE;
    }

    public function loadMore(): void
    {
        $this->perPage = min($this->perPage + self::PAGE, self::MAX);
    }

    public function webhookUrl(): string
    {
        // The real, working campaign-intake endpoint (Phase 9b, channel "ahc").
        return url('/ahc/campaign/lead-store');
    }

    public function markAllInquiry(CampaignStatsService $stats): void
    {
        $n = LeadDetail::where('campaign_id', $this->campaign->id)
            ->where(fn (Builder $q) => $q->whereNull('lead_tag')->orWhere('lead_tag', ''))
            ->update(['lead_tag' => 'Inquiry']);

        $stats->flushCampaign($this->campaign->id);
        Flux::toast(variant: 'success', text: __(':n leads tagged Inquiry.', ['n' => number_format($n)]));
    }

    public function addLead(CampaignStatsService $stats): void
    {
        $data = $this->validate([
            'lead.first_name' => 'required|string|max:120',
            'lead.last_name' => 'nullable|string|max:120',
            'lead.email' => 'nullable|email|max:190',
            'lead.contact' => 'required|string|max:40',
            'lead.address' => 'nullable|string|max:190',
            'lead.city' => 'nullable|string|max:120',
            'lead.state' => 'nullable|string|max:120',
            'lead.zip_code' => 'nullable|string|max:20',
        ])['lead'];

        // Model create() fires the LeadCreated observer once.
        LeadDetail::create($data + [
            'campaign_id' => $this->campaign->id,
            'user_id' => (string) auth()->id(),
            'status' => 0,
            'lead_type' => 'Manual',
        ]);

        $stats->flushCampaign($this->campaign->id);
        $this->reset('lead', 'showAdd');
        $this->perPage = self::PAGE;
        Flux::toast(variant: 'success', text: __('Lead added to :name.', ['name' => $this->campaign->name]));
    }

    /*
    |--------------------------------------------------------------------------
    | Edit campaign
    |--------------------------------------------------------------------------
    */
    public function openEdit(): void
    {
        $this->ceName = (string) $this->campaign->name;
        $this->ceType = (string) $this->campaign->price_type;
        $this->ceStatus = (string) ($this->campaign->status ?? 1);
        $this->showEdit = true;
    }

    public function saveCampaign(): void
    {
        $data = $this->validate([
            'ceName' => 'required|string|max:190',
            'ceType' => 'nullable|string|max:60',
            'ceStatus' => 'required|in:0,1,2',
        ]);

        $this->campaign->update([
            'name' => $data['ceName'],
            'price_type' => $data['ceType'] ?: null,
            'status' => (int) $data['ceStatus'],
        ]);
        $this->campaign->refresh();
        $this->showEdit = false;
        Flux::toast(variant: 'success', text: __('Campaign updated.'));
    }

    /*
    |--------------------------------------------------------------------------
    | Import CSV — header-matched rows into this campaign
    |--------------------------------------------------------------------------
    */
    public function updatedImportFile(): void
    {
        $this->reset('imported', 'skipped', 'importError', 'importPreview');

        try {
            $this->validateOnly('importFile', ['importFile' => 'required|file|mimes:csv,txt|max:20480']);
            $this->importPreview = array_slice(array_keys($this->csvRows()[0] ?? []), 0, 40);
        } catch (\Throwable $e) {
            $this->importError = $e->getMessage();
        }
    }

    public function importCsv(CampaignStatsService $stats): void
    {
        $this->validate(['importFile' => 'required|file|mimes:csv,txt|max:20480']);
        $this->reset('imported', 'skipped', 'importError');

        $fillable = (new LeadDetail)->getFillable();
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($fillable, &$imported, &$skipped) {
            foreach (array_chunk($this->csvRows(), 500) as $chunk) {
                foreach ($chunk as $row) {
                    $data = array_filter(
                        array_intersect_key($row, array_flip($fillable)),
                        fn ($v) => $v !== '' && $v !== null,
                    );

                    if (empty($data['first_name']) && empty($data['last_name']) && empty($data['contact']) && empty($data['email'])) {
                        $skipped++;

                        continue;
                    }

                    LeadDetail::create($data + [
                        'campaign_id' => $this->campaign->id,
                        'user_id' => (string) auth()->id(),
                        'lead_type' => $data['lead_type'] ?? 'Import',
                    ]);
                    $imported++;
                }
            }
        });

        $stats->flushCampaign($this->campaign->id);
        $this->imported = $imported;
        $this->skipped = $skipped;
        $this->reset('importFile', 'importPreview');
        $this->perPage = self::PAGE;
    }

    /** @return list<array<string,string|null>> header-keyed rows */
    private function csvRows(): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->importFile->get()) ?: [];
        $header = null;
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);

            if ($header === null) {
                $header = array_map(fn ($h) => str_replace(' ', '_', strtolower(trim((string) $h))), $cells);

                continue;
            }

            if (count(array_filter($cells, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null));
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | AI Scores — coverage summary + a capped status-heuristic fill
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function scoreSummary(): array
    {
        $row = DB::table('lead_ai_scores as s')
            ->join('lead_details as l', 'l.id', '=', 's.lead_id')
            ->where('l.campaign_id', $this->campaign->id)
            ->selectRaw('count(*) as scored, coalesce(round(avg(s.score)),0) as avg_score,
                sum(case when s.score >= 70 then 1 else 0 end) as hot,
                sum(case when s.score between 40 and 69 then 1 else 0 end) as warm,
                sum(case when s.score < 40 then 1 else 0 end) as cold')
            ->first();

        return [
            'scored' => (int) ($row->scored ?? 0),
            'avg' => (int) ($row->avg_score ?? 0),
            'hot' => (int) ($row->hot ?? 0),
            'warm' => (int) ($row->warm ?? 0),
            'cold' => (int) ($row->cold ?? 0),
            'ai_enabled' => (bool) config('ai.enabled'),
        ];
    }

    public function generateScores(): void
    {
        // Status-based fallback (no LLM call). Real model scoring is a Blast
        // Hub / Phase 11 job; here we just fill gaps for this campaign in
        // capped batches so the button is safe on a huge campaign.
        $map = [1 => 92, 4 => 68, 2 => 58, 3 => 55, 9 => 74, 11 => 28, 0 => 20];

        $leads = LeadDetail::where('campaign_id', $this->campaign->id)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('lead_ai_scores')
                ->whereColumn('lead_ai_scores.lead_id', 'lead_details.id'))
            ->limit(self::SCORE_BATCH)
            ->get(['id', 'status']);

        if ($leads->isEmpty()) {
            Flux::toast(variant: 'success', text: __('Every lead in this campaign already has a score.'));

            return;
        }

        $now = now();
        LeadAiScore::insert($leads->map(fn ($l) => [
            'lead_id' => $l->id,
            'score' => $map[(int) $l->status] ?? 25,
            'reason' => 'status-fallback',
            'scored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        unset($this->scoreSummary);
        Flux::toast(variant: 'success', text: __(':n leads scored (status heuristic).', ['n' => number_format($leads->count())]));
    }

    public function render(CampaignStatsService $stats): View
    {
        $query = LeadDetail::query()
            ->where('campaign_id', $this->campaign->id)
            ->with('leadStatus:id,title')
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->tag === 'inquiry', fn (Builder $q) => $q->where('lead_tag', 'Inquiry'))
            ->when($this->tag === 'sms', fn (Builder $q) => $q->where('lead_tag', 'SMS'))
            ->when($this->tag === 'none', fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('lead_tag')->orWhere('lead_tag', '')))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('contact', 'like', "%{$this->search}%")));

        $leads = $query->orderByDesc('id')->limit($this->perPage)->get();

        return view('livewire.leads.campaign', [
            'kpi' => $stats->forCampaign($this->campaign->id),
            'leads' => $leads,
            'hasMore' => $leads->count() === $this->perPage && $this->perPage < self::MAX,
            'atCap' => $this->perPage >= self::MAX,
            'statuses' => LeadStatus::orderBy('title')->pluck('title', 'id'),
        ])->layout('layouts.app', ['title' => $this->campaign->name ?: 'Campaign']);
    }
}
