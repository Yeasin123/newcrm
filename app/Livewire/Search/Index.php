<?php

namespace App\Livewire\Search;

use App\Models\CampaignFile;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Services\Search\SmartSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * CRM-wide lookup. Two structured forms (customers / leads) plus a
 * natural-language "Smart Search" box that SmartSearchService turns into the
 * same structured filters (or a plain keyword when the LLM gateway is off).
 * A jump-to search, not a report — capped at 50 hits per run.
 */
class Index extends Component
{
    private const LIMIT = 50;

    #[Url]
    public string $tab = 'customers';

    /** Natural-language box. */
    public string $smart = '';

    public ?string $smartNote = null;

    /** @var array<string,string> */
    public array $cf = ['name' => '', 'email' => '', 'phone' => '', 'city' => '', 'policy' => ''];

    /** @var array<string,string> */
    public array $lf = ['name' => '', 'email' => '', 'phone' => '', 'city' => '', 'campaign' => ''];

    /** Extra filters the Smart box may set that have no visible input. */
    public string $smartStatus = '';

    public string $smartPlan = '';

    public string $smartSalesman = '';

    public string $smartState = '';

    public bool $searched = false;

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['customers', 'leads'], true) ? $tab : 'customers';
    }

    public function clear(): void
    {
        $this->reset('cf', 'lf', 'smart', 'smartNote', 'smartStatus', 'smartPlan', 'smartSalesman', 'smartState', 'searched');
    }

    public function search(): void
    {
        $this->resetSmartExtras();
        $this->smartNote = null;
        $this->runSearch('manual');
    }

    public function runSmart(SmartSearchService $smart): void
    {
        $this->resetSmartExtras();

        $result = $smart->interpret($this->smart);
        $f = $result['filters'];

        if (($result['entity'] ?? null) !== null) {
            $this->tab = $result['entity'];
        }

        $target = $this->tab === 'leads' ? 'lf' : 'cf';
        $this->{$target}['name'] = $f['name'] ?? ($this->{$target}['name'] ?: '');
        $this->{$target}['email'] = $f['email'] ?? '';
        $this->{$target}['phone'] = $f['phone'] ?? '';
        $this->{$target}['city'] = $f['city'] ?? '';

        if ($this->tab === 'customers') {
            $this->cf['policy'] = $f['policy_number'] ?? '';
        }

        $this->smartStatus = $f['status'] ?? '';
        $this->smartPlan = $f['plan'] ?? '';
        $this->smartSalesman = $f['salesman'] ?? '';
        $this->smartState = $f['state'] ?? '';
        $this->smartNote = $result['note'];

        $this->runSearch('smart');
    }

    private function resetSmartExtras(): void
    {
        $this->smartStatus = $this->smartPlan = $this->smartSalesman = $this->smartState = '';
    }

    private function runSearch(string $via): void
    {
        $this->searched = true;

        Log::info('search.performed', [
            'via' => $via, 'tab' => $this->tab, 'user_id' => auth()->id(),
        ]);
    }

    /** @return array{results:Collection, applied:int} */
    private function customerResults(): array
    {
        $q = Client::query();
        $applied = 0;

        $name = trim($this->cf['name']);
        if (mb_strlen($name) >= 3) {
            $applied++;
            $q->where(fn (Builder $w) => $w
                ->where('first_name', 'like', "%{$name}%")
                ->orWhere('last_name', 'like', "%{$name}%"));
        }

        $email = trim($this->cf['email']);
        if (str_contains($email, '@')) {
            $applied++;
            $q->where('email', 'like', "%{$email}%");
        }

        $phone = preg_replace('/\D+/', '', $this->cf['phone']);
        if (mb_strlen((string) $phone) >= 4) {
            $applied++;
            $q->where('contact', 'like', "%{$phone}%");
        }

        $city = trim($this->cf['city']);
        if (mb_strlen($city) >= 3) {
            $applied++;
            $q->where('city', 'like', "%{$city}%");
        }

        $policy = trim($this->cf['policy']);
        if ($policy !== '') {
            $applied++;
            $q->where('policy_number', 'like', "%{$policy}%");
        }

        $applied += $this->applyCustomerSmartFilters($q);

        return [
            'results' => $applied === 0 ? collect() : $q->orderByDesc('id')->limit(self::LIMIT)
                ->with('propertyPackage:id,title')
                ->get(['id', 'policy_number', 'first_name', 'last_name', 'email', 'contact', 'city', 'state', 'status', 'payment_structure', 'salesman_name', 'package']),
            'applied' => $applied,
        ];
    }

    private function applyCustomerSmartFilters(Builder $q): int
    {
        $applied = 0;

        if ($this->smartStatus !== '') {
            $applied++;
            match ($this->smartStatus) {
                'active' => $q->where('status', 1),
                'failed', 'inactive' => $q->where('status', 0),
                'bypass' => $q->where('status', 5),
                'cancelled' => $q->where('login_off', 1),
                'renewal' => $q->where('is_renew', 1),
                default => null,
            };
        }

        if ($this->smartPlan !== '') {
            $applied++;
            $q->where('payment_structure', $this->smartPlan === 'monthly' ? '1' : '2');
        }

        if ($this->smartSalesman !== '') {
            $applied++;
            $q->where('salesman_name', 'like', "%{$this->smartSalesman}%");
        }

        if ($this->smartState !== '') {
            $applied++;
            $q->where('state', 'like', "%{$this->smartState}%");
        }

        return $applied;
    }

    /** @return array{results:Collection, applied:int} */
    private function leadResults(): array
    {
        $q = LeadDetail::query();
        $applied = 0;

        $name = trim($this->lf['name']);
        if (mb_strlen($name) >= 3) {
            $applied++;
            $q->where(fn (Builder $w) => $w
                ->where('first_name', 'like', "%{$name}%")
                ->orWhere('last_name', 'like', "%{$name}%"));
        }

        $email = trim($this->lf['email']);
        if (str_contains($email, '@')) {
            $applied++;
            $q->where('email', 'like', "%{$email}%");
        }

        $phone = preg_replace('/\D+/', '', $this->lf['phone']);
        if (mb_strlen((string) $phone) >= 4) {
            $applied++;
            $q->where('contact', 'like', "%{$phone}%");
        }

        $city = trim($this->lf['city']);
        if (mb_strlen($city) >= 3) {
            $applied++;
            $q->where('city', 'like', "%{$city}%");
        }

        if ($this->lf['campaign'] !== '') {
            $applied++;
            $q->where('campaign_id', $this->lf['campaign']);
        }

        if ($this->smartState !== '') {
            $applied++;
            $q->where('state', 'like', "%{$this->smartState}%");
        }

        return [
            'results' => $applied === 0 ? collect() : $q->orderByDesc('id')->limit(self::LIMIT)
                ->with(['leadStatus:id,title', 'campaign:id,name'])
                ->get(['id', 'first_name', 'last_name', 'email', 'contact', 'city', 'state', 'campaign_id', 'status', 'created_at']),
            'applied' => $applied,
        ];
    }

    public function render(): View
    {
        $customers = ['results' => collect(), 'applied' => 0];
        $leads = ['results' => collect(), 'applied' => 0];

        if ($this->searched) {
            $this->tab === 'leads'
                ? $leads = $this->leadResults()
                : $customers = $this->customerResults();
        }

        return view('livewire.search.index', [
            'customers' => $customers['results'],
            'leads' => $leads['results'],
            'customerFilterCount' => $customers['applied'],
            'leadFilterCount' => $leads['applied'],
            'campaigns' => CampaignFile::orderBy('name')->pluck('name', 'id'),
        ])->layout('layouts.app', ['title' => 'Search']);
    }
}
