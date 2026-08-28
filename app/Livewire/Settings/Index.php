<?php

namespace App\Livewire\Settings;

use App\Models\Brand;
use App\Models\BuyNumber;
use App\Models\Role;
use App\Models\System;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Settings hub — one page, a left sub-nav (ADMIN / PERSONAL) and a
 * swap-in panel per section. Fully wired here: Branding, Sale Animations,
 * Permissions & Roles, User Accounts. Commission Rates embeds the existing
 * Commissions\Settings component; the remaining admin sections link out to
 * the module that owns them.
 */
class Index extends Component
{
    /** section slug => [group, label, icon] */
    public const SECTIONS = [
        'packages' => ['admin', 'Packages & Pricing', 'archive-box'],
        'addons' => ['admin', 'Add-on Services', 'plus-circle'],
        'users' => ['admin', 'User Accounts', 'users'],
        'commissions' => ['admin', 'Commission Rates', 'percent-badge'],
        'gateway' => ['admin', 'Payment Gateway', 'credit-card'],
        'permissions' => ['admin', 'Permissions & Roles', 'shield-check'],
        'branding' => ['admin', 'Branding', 'paint-brush'],
        'animations' => ['admin', 'Sale Animations', 'sparkles'],
        'integrations' => ['admin', 'Integrations', 'globe-alt'],
        'security' => ['admin', 'Security & Access', 'lock-closed'],
        'numbers' => ['admin', 'Buy Numbers', 'phone'],
        'claims' => ['admin', 'Claim Settings', 'wrench-screwdriver'],
        'profile' => ['personal', 'My Profile', 'user'],
        'appearance' => ['personal', 'Appearance', 'sun'],
        'preferences' => ['personal', 'Preferences', 'eye'],
    ];

    /** The 4 editable role columns → the roles.name keyword that identifies each. */
    public const ROLE_COLUMNS = [
        'admin' => 'Admin (superadmin)',
        'manager' => 'Manager',
        'salesman' => 'Salesman',
        'cs' => 'CS Agent',
    ];

    /** Permission groups → the real free-text strings the app enforces. */
    public const PERMISSION_GROUPS = [
        'Customers' => ['Customers', 'Sale Sheet'],
        'Leads & Campaigns' => ['Main Page', 'Leads Status', 'Lead Blaster'],
        'Sales Team' => ['Salesman', 'Commissions', 'Earnings'],
        'Billing & Payments' => ['Payment failed(VB)', 'Renewal(VB)', 'Payment Health', 'failed-payments.view', 'failed-payments.settings'],
        'Service' => ['Claims', 'Technicians'],
        'Tools' => ['Search'],
        'System' => ['Settings'],
    ];

    #[Url]
    public string $section = 'branding';

    // ---- Branding ----
    /** @var array<string,string> */
    public array $branding = [];

    // ---- Sale animations ----
    /** @var array<string, list<string>> plan key => selected animation keys */
    public array $animations = [];

    // ---- Permissions matrix ----
    /** @var array<string, array<string, bool>> permission => (role column => granted) */
    public array $matrix = [];

    // ---- Preferences (per-user) ----
    /** @var array<string,mixed> */
    public array $prefs = [];

    // ---- My Profile ----
    /** @var array<string,string> */
    public array $profile = [];

    public string $newPassword = '';

    // ---- Security & Access (global) ----
    /** @var array<string,mixed> */
    public array $security = [];

    // ---- Claim Settings ----
    public string $claimFee = '';

    public string $newBrand = '';

    public string $newSystem = '';

    // ---- Buy Numbers ----
    public string $newNumber = '';

    public string $newNumberLabel = '';

    // ---- Integrations ----
    /** @var array<string, array{url:string, enabled:bool}> */
    public array $zapier = [];

    public string $sheetsId = '';

    public function mount(SettingsRepository $repo, ?string $section = null): void
    {
        if ($section !== null && array_key_exists($section, self::SECTIONS)) {
            $this->section = $section;
        }

        if (! array_key_exists($this->section, self::SECTIONS)) {
            $this->section = 'branding';
        }

        $this->branding = array_merge([
            'company_name' => 'American Home Coverage',
            'support_email' => 'support@americanhomecoverage.com',
            'support_phone' => '888-920-8280',
            'website_url' => 'https://americanhomecoverage.com',
        ], (array) $repo->get('branding', []));

        $this->animations = array_merge(
            array_fill_keys(array_keys($this->planTypes()), []),
            (array) $repo->get('animations', ['monthly' => ['confetti']]),
        );

        $this->prefs = array_merge([
            'compact_tables' => false,
            'sidebar_counts' => true,
            'auto_collapse' => true,
            'dashboard_ai' => true,
            'default_customers_tab' => 'all',
        ], (array) $repo->get('pref:'.auth()->id(), []));

        $this->security = array_merge([
            'session_timeout' => 30,
            'strong_passwords' => true,
            'login_lockout' => true,
            'lockout_max' => 5,
            'lockout_window' => 15,
            'twofa' => false,
            'ip_allowlist' => false,
        ], (array) $repo->get('security', []));

        $u = auth()->user();
        $this->profile = [
            'first_name' => (string) $u?->first_name,
            'last_name' => (string) $u?->last_name,
            'email' => (string) $u?->email,
            'contact' => (string) $u?->contact,
        ];

        $this->claimFee = (string) $repo->get('claim_service_fee', config('sale_sheet.default_call_fee', 95));

        $this->zapier = array_merge(
            collect($this->zapierEvents())->mapWithKeys(fn ($v, $k) => [$k => ['url' => '', 'enabled' => false]])->all(),
            (array) $repo->get('zapier', []),
        );
        $this->sheetsId = (string) $repo->get('google_sheets_id', '');

        $this->loadMatrix();
    }

    public function zapierEvents(): array
    {
        return [
            'payment_failed' => 'Payment Failed — AN reports a declined payment',
            'payment_recovered' => 'Payment Recovered — failed payment collected on the board',
            'payment_resolved' => 'Payment Resolved — failed payment recovered (retry / manual)',
            'renewal' => 'Renewal — customer marked Renewed on the Renewal board',
            'sale' => 'Sale — a new policy is created',
            'edit_plan' => 'Edit Plan — a customer plan / package is edited',
            'upgrade' => 'Upgrade — a customer is upgraded to a higher tier',
            'star' => 'Star — a customer is starred',
            'dispute' => 'Dispute — a policy is flagged as disputed',
        ];
    }

    /** Real inbound webhook URLs this CRM exposes. */
    public function inboundWebhooks(): array
    {
        $u = fn ($path) => url($path);

        return [
            ['Authorize.Net Notify — Create', $u('/api/transaction/create/notify'), 'HMAC-verified; dedupes; always 200.'],
            ['Authorize.Net Notify — Suspend', $u('/api/transaction/suspend/notify'), 'Flips clients.status = 0, backfills payments.'],
            ['Authorize.Net Notify — Terminated', $u('/api/transaction/terminated/notify'), 'Takes the customer offline.'],
            ['Campaign Lead Intake (AHC)', $u('/ahc/campaign/lead-store'), 'API-key partner lead intake → lead_details.'],
            ['Replit Claim Status', $u('/replit/claim/status'), 'Inbound claim-status updates; writes claim_webhook_logs.'],
            ['Salesman SMS Inbound (Telnyx)', $u('/telnyx/salesman-messages/webhook'), 'Inbound texts to the salesman inbox.'],
            ['Entrance SMS Reply — First', $u('/first/entrance'), 'Numbered lead-entrance SMS reply webhook.'],
        ];
    }

    /* -------------------------------------------------------- preferences */

    public function savePreferences(SettingsRepository $repo): void
    {
        $repo->set('pref:'.auth()->id(), $this->prefs);
        Flux::toast(variant: 'success', text: __('Preferences saved.'));
    }

    /* ------------------------------------------------------------ profile */

    public function saveProfile(): void
    {
        $data = $this->validate([
            'profile.first_name' => 'required|string|max:120',
            'profile.last_name' => 'nullable|string|max:120',
            'profile.email' => 'required|email|max:190|unique:users,email,'.auth()->id(),
            'profile.contact' => 'nullable|string|max:40',
            'newPassword' => 'nullable|string|min:8',
        ])['profile'];

        $update = $data;
        if (filled($this->newPassword)) {
            $update['password'] = Hash::make($this->newPassword);
        }

        auth()->user()->update($update);
        $this->newPassword = '';
        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /* ----------------------------------------------------------- security */

    public function saveSecurity(SettingsRepository $repo): void
    {
        $repo->set('security', $this->security);
        Flux::toast(variant: 'success', text: __('Security settings saved.'));
    }

    /* ------------------------------------------------------- claim settings */

    public function saveClaimFee(SettingsRepository $repo): void
    {
        $this->validate(['claimFee' => 'required|numeric|min:0']);
        $repo->set('claim_service_fee', $this->claimFee);
        Flux::toast(variant: 'success', text: __('Service call fee saved.'));
    }

    public function addBrand(): void
    {
        $name = trim($this->newBrand);
        if ($name === '') {
            return;
        }

        if (! Brand::where('title', $name)->exists()) {
            Brand::create(['title' => $name]);
        }
        $this->newBrand = '';
    }

    public function removeBrand(int $id): void
    {
        Brand::whereKey($id)->delete();
    }

    public function addSystem(): void
    {
        $name = trim($this->newSystem);
        if ($name === '') {
            return;
        }

        if (! System::where('title', $name)->exists()) {
            System::create(['title' => $name]);
        }
        $this->newSystem = '';
    }

    public function removeSystem(int $id): void
    {
        System::whereKey($id)->delete();
    }

    /* --------------------------------------------------------- buy numbers */

    public function addNumber(): void
    {
        $n = preg_replace('/[^\d]/', '', $this->newNumber);
        if (strlen((string) $n) < 7) {
            Flux::toast(variant: 'warning', text: __('Enter a valid phone number.'));

            return;
        }

        BuyNumber::create([
            'contact_no' => $this->newNumber,
            'user_id' => auth()->id(),
            'state' => $this->newNumberLabel ?: null,
            'lock_number' => 1,
        ]);
        $this->reset('newNumber', 'newNumberLabel');
        Flux::toast(variant: 'success', text: __('DID number added.'));
    }

    public function toggleNumber(int $id): void
    {
        $num = BuyNumber::find($id);
        if ($num) {
            $num->update(['lock_number' => $num->lock_number ? 0 : 1]);
        }
    }

    public function removeNumber(int $id): void
    {
        BuyNumber::whereKey($id)->delete();
    }

    /* ------------------------------------------------------- integrations */

    public function saveIntegrations(SettingsRepository $repo): void
    {
        $repo->set('zapier', $this->zapier);
        $repo->set('google_sheets_id', $this->sheetsId);
        Flux::toast(variant: 'success', text: __('Integration settings saved.'));
    }

    /* -------------------------------------------------------- catalogs */

    public function planTypes(): array
    {
        return [
            'monthly' => ['1 mo', 'Monthly Plan'],
            '1year' => ['1 yr', '1-Year Plan'],
            '2year' => ['2 yr', '2-Year Plan'],
            'vip3' => ['VIP 3', 'VIP 3-Year Plan'],
            'vip6' => ['VIP 6', 'VIP 6-Year Plan'],
        ];
    }

    public function animationCatalog(): array
    {
        return [
            'confetti' => ['🎉', 'Confetti', 'Colorful confetti burst — default for all plans'],
            'basketball' => ['🏀', 'Basketball', 'Ball arcs cleanly into the hoop — great for multi-year deals'],
            'slot' => ['🎰', 'Slot Machine', 'Spinning reels land on jackpot — thrilling for VIP'],
            'fireworks' => ['🎆', 'Fireworks', 'Firework bursts — celebratory for big closings'],
            'money' => ['💰', 'Money Rain', 'Cash rains from the top — Money in the Bank!'],
            'rocket' => ['🚀', 'Rocket Launch', "Rocket blasts off with a trail of fire — sky's the limit!"],
            'trophy' => ['🏆', 'Trophy', 'Golden trophy reveal with sparkles — for your biggest winners'],
            'champagne' => ['🍾', 'Champagne Pop', 'Cork flies, bubbles erupt — time to celebrate!'],
            'diamond' => ['💎', 'Diamond Rain', 'Gems shower down — for luxury, high-value closes'],
            'lightning' => ['⚡', 'Lightning', 'Electric bolt strikes — fast, charged, electric energy!'],
        ];
    }

    /* -------------------------------------------------------- branding */

    public function saveBranding(SettingsRepository $repo): void
    {
        $this->validate([
            'branding.company_name' => 'required|string|max:190',
            'branding.support_email' => 'required|email|max:190',
            'branding.support_phone' => 'nullable|string|max:40',
            'branding.website_url' => 'nullable|url|max:190',
        ]);

        $repo->set('branding', $this->branding);
        Flux::toast(variant: 'success', text: __('Branding saved.'));
    }

    /* ------------------------------------------------------ animations */

    public function toggleAnimation(string $plan, string $anim): void
    {
        $set = $this->animations[$plan] ?? [];
        $this->animations[$plan] = in_array($anim, $set, true)
            ? array_values(array_diff($set, [$anim]))
            : [...$set, $anim];
    }

    public function saveAnimations(SettingsRepository $repo): void
    {
        $repo->set('animations', $this->animations);
        Flux::toast(variant: 'success', text: __('Sale animations saved.'));
    }

    /* ----------------------------------------------------- permissions */

    private function loadMatrix(): void
    {
        $roles = Role::all()->keyBy(fn ($r) => $this->roleColumnFor($r->name));

        foreach (self::PERMISSION_GROUPS as $perms) {
            foreach ($perms as $perm) {
                $this->matrix[$perm] = [
                    'admin' => true, // superadmin always
                    'manager' => in_array($perm, $roles->get('manager')?->permissions ?? [], true),
                    'salesman' => in_array($perm, $roles->get('salesman')?->permissions ?? [], true),
                    'cs' => in_array($perm, $roles->get('cs')?->permissions ?? [], true),
                ];
            }
        }
    }

    private function roleColumnFor(?string $name): string
    {
        $n = strtolower((string) $name);

        return match (true) {
            str_contains($n, 'manager') => 'manager',
            str_contains($n, 'customer service') || str_contains($n, 'cs ') => 'cs',
            str_contains($n, 'salesman') || str_contains($n, 'sales') => 'salesman',
            default => 'admin',
        };
    }

    public function savePermissions(): void
    {
        foreach (['manager', 'salesman', 'cs'] as $col) {
            $role = Role::all()->first(fn ($r) => $this->roleColumnFor($r->name) === $col);

            if (! $role) {
                continue;
            }

            $granted = collect($this->matrix)
                ->filter(fn ($cols) => ! empty($cols[$col]))
                ->keys()
                ->values()
                ->all();

            $role->update(['permissions' => $granted]);
        }

        Flux::toast(variant: 'success', text: __('Role permissions updated — changes apply immediately.'));
    }

    /* ---------------------------------------------------------- render */

    public function render(SettingsRepository $repo): View
    {
        $data = [
            'sections' => collect(self::SECTIONS)->map(fn ($m, $slug) => [
                'slug' => $slug, 'group' => $m[0], 'label' => $m[1], 'icon' => $m[2],
            ])->groupBy('group'),
            'meta' => self::SECTIONS[$this->section] ?? ['admin', 'Settings', 'cog-6-tooth'],
        ];

        if ($this->section === 'users') {
            $data['users'] = User::with('role:id,name')->orderBy('created_at')
                ->get(['id', 'first_name', 'last_name', 'email', 'role_id', 'type', 'status', 'created_at']);
        }

        if ($this->section === 'permissions') {
            $data['groups'] = self::PERMISSION_GROUPS;
        }

        if ($this->section === 'claims') {
            $data['systems'] = System::withCount('issues')->orderBy('title')->limit(200)->get();
            $data['brands'] = Brand::orderBy('title')->limit(300)->get();
        }

        if ($this->section === 'numbers') {
            $data['numbers'] = BuyNumber::latest('id')->limit(100)->get();
        }

        return view('livewire.settings.index', $data)->layout('layouts.app', ['title' => 'Settings']);
    }
}
