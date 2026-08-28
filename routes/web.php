<?php

use App\Http\Controllers\BlastHub\DownloadController;
use App\Http\Controllers\Customers\ExportController;
use App\Http\Controllers\Leads\CampaignExportController;
use App\Http\Controllers\Webhooks\CampaignLeadIntakeController;
use App\Http\Controllers\Webhooks\EmailWebhookController;
use App\Http\Controllers\Webhooks\EntranceReplyController;
use App\Http\Controllers\Webhooks\ReplitClaimController;
use App\Http\Controllers\Webhooks\SalesmanMessageInboundController;
use App\Http\Controllers\Webhooks\VanilasoftDialerController;
use App\Livewire\Ai\AutomationDashboard;
use App\Livewire\Billing\AuthorizeAccounts;
use App\Livewire\Billing\WebhookEvents;
use App\Livewire\Customers\Import;
use App\Livewire\Customers\Index;
use App\Livewire\Customers\Show;
use App\Livewire\EntranceBlast\Console;
use App\Livewire\FailedPayments\Settings;
use App\Livewire\Leads\Campaign;
use App\Livewire\PaymentFailed\Board;
use App\Livewire\Reporting\AnetHealth;
use App\Livewire\Reporting\FinanceCockpit;
use App\Livewire\Reporting\GatewayMonitor;
use App\Livewire\Reporting\WebhookReport;
use App\Livewire\SaleSheet\AddCustomer;
use App\Livewire\SalesmanMessages\Inbox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Phase 12 — public marketing site (owns `/` and the guest-facing pages).
require __DIR__.'/public.php';

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| Every module below is gated by auth + the `admin` middleware (legacy
| `type = 1`). Per-module `permission:` gates are layered on as each module
| is built out. Most routes are placeholders until their phase lands.
*/
Route::middleware(['auth', 'admin'])->group(function () {
    // Phase 10a — full dashboard
    Route::get('dashboard', App\Livewire\Dashboard\Index::class)->name('dashboard');

    // Phase 2 — Customers
    Route::middleware('permission:Customers')->group(function () {
        Route::get('customers', Index::class)->name('customers.index');
        Route::get('customers/export', ExportController::class)->name('customers.export');
        Route::get('customers/import', Import::class)->name('customers.import');
        Route::get('customers/{customer}', Show::class)->name('customers.show');
    });

    // Phase 3 — Leads, Sale Sheet, Search
    Route::middleware('permission:Main Page')->group(function () {
        Route::get('leads', App\Livewire\Leads\Index::class)->name('leads.index');
        Route::get('leads/campaign/{campaign}/export', CampaignExportController::class)->name('leads.campaign.export');
        Route::get('leads/campaign/{campaign}', Campaign::class)->name('leads.campaign');
        Route::get('leads/{lead}', App\Livewire\Leads\Show::class)->name('leads.show');
    });
    // Sale Sheet = the staff "Add Customer / new policy" wizard (Phase 12e).
    Route::middleware('permission:Sale Sheet')->group(function () {
        Route::get('sale-sheet', AddCustomer::class)->name('sale-sheet.index');
        Route::get('add', AddCustomer::class)->name('admin.client.add');
        Route::get('salesman/add/client', AddCustomer::class)->name('salesman.client.add');
    });
    Route::get('search', App\Livewire\Search\Index::class)
        ->middleware('permission:Search')->name('search.index');

    // Phase 4 — Sales team
    Route::middleware('permission:Salesman')->group(function () {
        Route::get('salesmen', App\Livewire\Salesmen\Index::class)->name('salesmen.index');
        Route::get('salesmen/{salesman}', App\Livewire\Salesmen\Show::class)->name('salesmen.show');
    });
    Route::middleware('permission:Commissions')->group(function () {
        Route::get('commissions', App\Livewire\Commissions\Index::class)->name('commissions.index');
        Route::get('commissions/settings', App\Livewire\Commissions\Settings::class)->name('commissions.settings');
    });
    Route::get('earnings', App\Livewire\Earnings\Index::class)
        ->middleware('permission:Earnings')->name('earnings.index');
    Route::get('activity', App\Livewire\Activity\Index::class)->name('activity.index');
    Route::get('board-history', App\Livewire\BoardHistory\Index::class)
        ->middleware('permission:Leads Status')->name('board-history.index');
    // Phase 9f-2 — salesman SMS inbox (superadmin-gated inside the component)
    Route::get('salesman-messages', Inbox::class)->name('salesman-messages.index');

    // Phase 5 — Claims & Technicians
    Route::middleware('permission:Claims')->group(function () {
        Route::get('claims', App\Livewire\Claims\Index::class)->name('claims.index');
        Route::get('claims/export', App\Http\Controllers\Claims\ExportController::class)->name('claims.export');
        Route::get('claims/{claim}', App\Livewire\Claims\Show::class)->name('claims.show');
    });
    Route::middleware('permission:Technicians')->group(function () {
        Route::get('technicians', App\Livewire\Technicians\Index::class)->name('technicians.index');
        Route::get('technicians/{technician}', App\Livewire\Technicians\Show::class)->name('technicians.show');
    });

    // Phase 6 — Billing config (Authorize.Net merchant accounts + webhook log)
    Route::middleware('permission:Settings')->group(function () {
        // Settings hub (branding / animations / permissions / users / …)
        Route::get('settings/hub/{section?}', App\Livewire\Settings\Index::class)->name('settings.hub');

        Route::get('billing/accounts', AuthorizeAccounts::class)->name('billing.accounts');
        Route::get('billing/webhook-events', WebhookEvents::class)->name('billing.webhook-events');
        Route::get('billing/settings', App\Livewire\Billing\Settings::class)->name('billing.settings');

        // Phase 10b — reporting cockpits
        Route::get('reports/finance-cockpit', FinanceCockpit::class)->name('reports.finance-cockpit');
        Route::get('reports/gateway-monitor', GatewayMonitor::class)->name('reports.gateway-monitor');
        Route::get('reports/anet-health', AnetHealth::class)->name('reports.anet-health');
        Route::get('reports/webhook-report', WebhookReport::class)->name('reports.webhook-report');

        // Phase 11 — lead automation status
        Route::get('automation/leads', AutomationDashboard::class)->name('automation.leads');
    });

    // Phase 8 — Failed Payment Module admin
    Route::middleware('failed-payments.settings.sync')->group(function () {
        Route::get('failed-payments', App\Livewire\FailedPayments\Index::class)
            ->middleware('permission:failed-payments.view')->name('failed-payments.index');
        Route::get('failed-payments/settings', Settings::class)
            ->middleware('permission:failed-payments.settings')->name('failed-payments.settings');
        Route::get('failed-payments/{failedPaymentCase}', App\Livewire\FailedPayments\Show::class)
            ->middleware('permission:failed-payments.view')->whereUuid('failedPaymentCase')
            ->name('failed-payments.show');
    });

    // Phase 9e — Blast Hub / Phase 9f-3 — Entrance Blast
    Route::middleware('permission:Lead Blaster')->group(function () {
        Route::get('blast-hub', App\Livewire\BlastHub\Index::class)->name('blast-hub.index');
        Route::get('blast-hub/download/{run}', DownloadController::class)
            ->name('blast-hub.download');
        Route::get('entrance-blast', Console::class)->name('entrance-blast.index');
    });

    // Phase 7 — Payment Failed VB, Renewals VB, Disputes, Payment Health
    Route::middleware('permission:Payment failed(VB)')->group(function () {
        Route::get('payment-failed', App\Livewire\PaymentFailed\Index::class)->name('payment-failed.index');
        Route::get('payment-failed/board', Board::class)->name('payment-failed.board');
    });
    Route::middleware('permission:Renewal(VB)')->group(function () {
        Route::get('renewals', App\Livewire\Renewals\Index::class)->name('renewals.index');
        Route::get('renewals/board', App\Livewire\Renewals\Board::class)->name('renewals.board');
    });
    Route::get('disputes', App\Livewire\Disputes\Index::class)->name('disputes.index');
    Route::get('financials', App\Livewire\Financials\Index::class)->name('financials.index');
    Route::get('payment-health', App\Livewire\PaymentHealth\Index::class)
        ->middleware('permission:Payment Health')->name('payment-health.index');
});

/*
|--------------------------------------------------------------------------
| Public webhooks (unauthenticated — verified in the handler)
|--------------------------------------------------------------------------
*/
Route::post('replit/claim/status', ReplitClaimController::class)
    ->name('webhooks.replit.claim');

Route::post('telnyx/salesman-messages/webhook', SalesmanMessageInboundController::class)
    ->name('telnyx.salesman-messages.webhook');

/*
| Inbound "entrance" SMS-reply webhooks (Phase 9a). One controller, one
| service; the channel slug is a route default that selects a
| config('entrances.channels.*') row. Paths + JSON bodies are byte-identical
| to the legacy per-endpoint controllers.
*/
foreach ([
    'zero-entrance' => ['zero', 'zero.entrance'],
    'first/entrance' => ['first', 'entrance'],
    'second/entrance' => ['second', 'second.entrance'],
    'third/entrance' => ['third', 'third.entrance'],
    'fourth-entrance' => ['fourth', 'fourth.entrance'],
    'fifth-entrance' => ['fifth', 'fifth.entrance'],
    'sixth-entrance' => ['sixth', 'sixth.entrance'],
    'seventh-entrance' => ['seventh', 'seventh.entrance'],
    'eigth-entrance' => ['eigth', 'eigth.entrance'],
    'nineth-entrance' => ['nineth', 'nineth.entrance'],
    'mm-webhook' => ['mm', 'mm.webhook'],
    'new/yair-webhook/link' => ['yair', 'yair.webhook'],
    'avchad-webhook' => ['avchad', 'avchad.webhook'],
    'clicksend/get/notification' => ['cs', 'clicksend.notification'],
] as $path => [$channel, $routeName]) {
    Route::post($path, EntranceReplyController::class)
        ->defaults('channel', $channel)
        ->name($routeName);
}

/*
| Partner campaign lead-intake links (Phase 9b). One controller + service,
| channel slug from the route default. Plain-text 'ok' / 'empty_data' bodies.
*/
foreach ([
    'ahc/campaign/lead-store' => 'ahc',
    'ahw/campaign/lead-store' => 'ahw',
    'lcl/campaign/lead-store' => 'lcl',
    'gbl/campaign/lead-store' => 'gbl',
    'jltwo/campaign/lead-store' => 'jltwo',
    'modanize/campaign/lead-store' => 'modanize',
] as $path => $channel) {
    Route::post($path, CampaignLeadIntakeController::class)
        ->defaults('channel', $channel)
        ->name('campaign-intake.'.$channel);
}

/*
| Email-platform lead webhooks (Phase 9c) — quote / pquote / CEL engagement,
| campaign create, email-link click, change-email, and the LG SMS reply.
*/
Route::post('quote/email/webhook', [EmailWebhookController::class, 'engagement'])
    ->defaults('channel', 'quote')->name('quote.email.webhook');
Route::post('pquote/email/webhook', [EmailWebhookController::class, 'engagement'])
    ->defaults('channel', 'pquote')->name('pquote.email.webhook');
Route::post('cel/webhook', [EmailWebhookController::class, 'engagement'])
    ->defaults('channel', 'cel')->name('ce.webhook');
Route::post('campaign/webhook', [EmailWebhookController::class, 'campaign'])->name('campaign.webhook');
Route::post('new/email/webhook', [EmailWebhookController::class, 'linkClick'])->name('email.webhook');
Route::post('second/new/webhook', [EmailWebhookController::class, 'secondNew'])->name('second.new.webhook');
Route::post('change/email/webhook', [EmailWebhookController::class, 'changeEmail'])->name('change.email.webhook');

/*
| Vanilasoft / 3CX dialer hooks (Phase 9d).
*/
Route::post('vanilasoft/user/info', [VanilasoftDialerController::class, 'userInfo'])->name('vanilasoft.user.info');
Route::post('check/salesman/id', [VanilasoftDialerController::class, 'checkSalesmanId'])->name('check.salesman.id');
Route::post('vanilasoft/viewboard/info', [VanilasoftDialerController::class, 'viewboardInfo'])->name('vanilasoft.viewboard.info');

require __DIR__.'/settings.php';
