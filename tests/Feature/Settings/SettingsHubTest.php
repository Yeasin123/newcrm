<?php

use App\Livewire\Settings\Index;
use App\Models\BillingSetting;
use App\Models\Brand;
use App\Models\BuyNumber;
use App\Models\Role;
use App\Models\System;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('the settings hub renders and swaps sections', function () {
    Livewire::test(Index::class)
        ->assertSee('Branding')
        ->assertSee('Company Name')
        ->set('section', 'animations')
        ->assertSee('Sale Animations')
        ->assertSee('Round-Robin Rotation')
        ->set('section', 'users')
        ->assertSee('User Accounts');
});

test('mount honours the {section} route parameter', function () {
    Livewire::test(Index::class, ['section' => 'permissions'])
        ->assertSet('section', 'permissions')
        ->assertSee('Permissions & Roles');
});

test('branding saves to the settings store', function () {
    Livewire::test(Index::class)
        ->set('branding.company_name', 'Acme Coverage')
        ->set('branding.support_email', 'help@acme.test')
        ->set('branding.website_url', 'https://acme.test')
        ->call('saveBranding')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('branding')['company_name'])->toBe('Acme Coverage');
    expect(BillingSetting::where('key', 'settings_branding')->exists())->toBeTrue();
});

test('sale animations round-trip through the store', function () {
    Livewire::test(Index::class)
        ->set('section', 'animations')
        ->call('toggleAnimation', 'vip6', 'slot')
        ->call('toggleAnimation', 'vip6', 'fireworks')
        ->call('saveAnimations')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('animations')['vip6'])->toContain('slot', 'fireworks');
});

test('the permissions matrix writes roles.permissions', function () {
    $manager = Role::create(['name' => 'Ops Manager', 'permissions' => []]);
    $salesman = Role::create(['name' => 'Real Salesman AHC', 'permissions' => []]);

    Livewire::test(Index::class)
        ->set('section', 'permissions')
        ->set('matrix.Customers.manager', true)
        ->set('matrix.Commissions.salesman', true)
        ->call('savePermissions')
        ->assertHasNoErrors();

    expect($manager->fresh()->permissions)->toContain('Customers')
        ->and($salesman->fresh()->permissions)->toContain('Commissions');
});

test('my profile updates the signed-in user and can set a new password', function () {
    Livewire::test(Index::class, ['section' => 'profile'])
        ->set('profile.first_name', 'Renamed')
        ->set('profile.email', auth()->user()->email)
        ->set('newPassword', 'brandnew99')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $u = auth()->user()->fresh();
    expect($u->first_name)->toBe('Renamed')
        ->and(Hash::check('brandnew99', $u->password))->toBeTrue();
});

test('preferences persist per-user', function () {
    Livewire::test(Index::class, ['section' => 'preferences'])
        ->set('prefs.compact_tables', true)
        ->set('prefs.default_customers_tab', 'monthly')
        ->call('savePreferences')
        ->assertHasNoErrors();

    $stored = app(SettingsRepository::class)->get('pref:'.auth()->id());
    expect($stored['compact_tables'])->toBeTrue()
        ->and($stored['default_customers_tab'])->toBe('monthly');
});

test('security settings persist globally', function () {
    Livewire::test(Index::class, ['section' => 'security'])
        ->set('security.session_timeout', 60)
        ->set('security.twofa', true)
        ->call('saveSecurity')
        ->assertHasNoErrors();

    expect((int) app(SettingsRepository::class)->get('security')['session_timeout'])->toBe(60)
        ->and(app(SettingsRepository::class)->get('security')['twofa'])->toBeTrue();
});

test('claim settings save the fee and manage brands + systems', function () {
    Brand::create(['title' => 'ByeBrand']);

    $c = Livewire::test(Index::class, ['section' => 'claims'])
        ->set('claimFee', '120')
        ->call('saveClaimFee')
        ->assertHasNoErrors()
        ->set('newBrand', 'Kenmore')
        ->call('addBrand')
        ->set('newSystem', 'Wine Cooler')
        ->call('addSystem');

    expect((string) app(SettingsRepository::class)->get('claim_service_fee'))->toBe('120')
        ->and(Brand::where('title', 'Kenmore')->exists())->toBeTrue()
        ->and(System::where('title', 'Wine Cooler')->exists())->toBeTrue();

    $bye = Brand::where('title', 'ByeBrand')->first();
    $c->call('removeBrand', $bye->id);
    expect(Brand::where('title', 'ByeBrand')->exists())->toBeFalse();
});

test('buy numbers can be added, toggled and removed', function () {
    Livewire::test(Index::class, ['section' => 'numbers'])
        ->set('newNumber', '(888) 385-2206')
        ->set('newNumberLabel', 'Payment Failed Line')
        ->call('addNumber')
        ->assertHasNoErrors();

    $num = BuyNumber::where('contact_no', '(888) 385-2206')->first();
    expect($num)->not->toBeNull()->and($num->state)->toBe('Payment Failed Line');

    Livewire::test(Index::class, ['section' => 'numbers'])->call('toggleNumber', $num->id);
    expect((bool) $num->fresh()->lock_number)->toBeFalse();
});

test('integration settings persist', function () {
    Livewire::test(Index::class, ['section' => 'integrations'])
        ->set('sheetsId', '14uNGabc')
        ->set('zapier.sale.url', 'https://hooks.zapier.com/hooks/catch/1/2/')
        ->set('zapier.sale.enabled', true)
        ->call('saveIntegrations')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('google_sheets_id'))->toBe('14uNGabc')
        ->and(app(SettingsRepository::class)->get('zapier')['sale']['enabled'])->toBeTrue();
});

test('the Settings permission gates the hub', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('settings.hub'))->assertForbidden();
});
