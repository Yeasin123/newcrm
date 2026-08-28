# PROJECT_KNOWLEDGE.md

> Persistent technical map of the American Home Coverage (AHC) Laravel CRM.
> Generated from direct source inspection (analysis-only session, no code changes made).
> Read this file FIRST before making changes. Where something below is marked
> "not verified" / "inferred", re-read the actual source before relying on it —
> this map reflects what was directly confirmed, not a guarantee about the
> ~140 files that were not opened.

---

## 1. Application Purpose

**American Home Coverage** (`APP_NAME` in `.env`) is a home-warranty-style
insurance/service CRM. It covers:

- Lead capture (multiple inbound webhook sources) → lead routing/scoring →
  conversion to paying client.
- Policy checkout and recurring subscription billing via **Authorize.Net**
  (CIM customer/payment profiles + ARB subscriptions).
- Customer self-service portal (claims, agreements, payment history).
- Admin back-office: clients, claims, payments, salesmen, technicians,
  reporting, telephony integrations.
- A **Failed Payment Module** (actively being built on the `failed-payment-module`
  branch) that passively captures Authorize.Net billing failures into a
  case-management system with retry scheduling and admin workflow.
- A secondary **Real Estate** product bolted onto the same codebase
  (separate `agent` auth guard, companies, property orders).

---

## 2. Laravel Architecture

- **Framework**: Laravel `^9.19`, PHP `^8.0.2` (`composer.json`).
- **Auth**: 3 independent session guards, no SPA/token frontend (Sanctum
  installed but only used for one `/api/user` route).
- **Frontend**: Blade + Alpine.js + Tailwind + Vite (`package.json`). No
  Vue/React. Server-rendered multi-page app.
- **No queue workers**: `QUEUE_CONNECTION=sync` in `.env.example`, and there
  is **no `app/Jobs/` directory**. All "background processing" is the Laravel
  scheduler (`app/Console/Kernel.php`) running artisan commands synchronously
  via cron.
- **No repository/DTO layer convention**. Controllers are large and do most
  of the work; a real service layer only exists cleanly for the newer
  Failed Payment module (`app/Services/FailedPayment/*`, interface-bound).
- **Only one Eloquent Policy** in the app: `App\Policies\FailedPaymentCasePolicy`,
  registered in `App\Providers\AuthServiceProvider`.
- **Only one active model Observer**: `LeadDetailObserver` on `LeadDetail`
  (`App\Providers\AppServiceProvider::boot()`). Observers for `Client`,
  `PaymentHistory`, and `Claim` exist as classes but are **commented out**:
  ```php
  // Client::observe(ClientObserver::class);
  // PaymentHistory::observe(PaymentHistoryObserver::class);
  // Claim::observe(ClaimObserver::class);
  LeadDetail::observe(LeadDetailObserver::class);
  ```
  (`app/Providers/AppServiceProvider.php:113-116`)
- **Route files are split by concern**, not just `web.php`/`api.php`:
  - `routes/web.php` — public site, client portal, Authorize.Net payment endpoints
  - `routes/admin.php` — the entire admin panel (845 lines)
  - `routes/api.php` — Authorize.Net webhook notify endpoints + a few checkout endpoints
  - `routes/agent.php` — Real Estate agent portal
  - `routes/auth.php` — Breeze-style admin auth scaffolding
  - `routes/failed_payments.php` — Failed Payment Module admin UI
  - `routes/anet_recurring_health.php`, `routes/finance_cockpit.php`,
    `routes/gateway_monitor.php`, `routes/payment_health.php`,
    `routes/project_agent.php` — smaller admin sub-tools

---

## 3. Major Modules

| Module | Key controllers/services | Notes |
|---|---|---|
| Lead intake | `Admin\ZeroEntranceController` … `Admin\NinethEntranceController` (10 numbered webhook controllers), `LeadLeadailApiLinkController`, `ModanizeCampaignController` | Each "entrance" is a distinct inbound lead-source webhook |
| Lead routing/rules | `Rule\LeadRoutingController`, `Rule\CadenceController`, `Rule\LeadSourceContoller`, `Rule\LeadStatusController`, `Rule\ResultCodeController`, `Rule\TimePeriodController`, `Rule\TimeZoneController` | Under `routes/admin.php` prefix `rules` |
| Checkout / plan selection | `PlanCheckoutController`, `ShopPlansController`, `AdditionalCheckoutController` | Zip lookup, package/addon pricing, promo codes (`Admin\PromotionController`) |
| Client/policy admin | `Admin\ClientsController`, `Admin\ClientViewBoardController`, `Admin\Renew\RenewViewboardController`, `Admin\SignedPdfCopyController`, `Admin\CanceledMonthlyClientsController` | |
| Customer portal | `Clint\DashboardController`, `Clint\ClimController` (claims), `Clint\AgreementController`, `Clint\SettingController`, `Clint\EarningsController` | Guard `user` |
| Claims | `Admin\ClaimController`, `Admin\NewClaimController`, `Admin\ClaimTrackController`, `Admin\ClaimImportController`, `Admin\PartsClaimController` | |
| Payments/billing | `Payment\AuthorizeNetPaymentController`, `Payment\StripePaymentController`, `Admin\SubscriptionController` (not fully traced), `Admin\TransactionCheckController`, `Admin\PaymentRefundController`, `Admin\PaymentFromClientInvoiceController` | See §11–14 |
| Failed Payment Module | `Admin\FailedPaymentController`, `Admin\FailedPaymentWorkflowController`, `Admin\FailedPaymentReportController`, `Admin\FailedPaymentSettingsController`, `app/Services/FailedPayment/*` | New, feature-flagged, see §13 |
| Communications | `Admin\TelynxController`, `Admin\SalesmanMessageController`, `Admin\ClickSendController`, `Admin\VoiceController`, `Admin\VoicemailController`, `Admin\SmsSignatureController` | Twilio + Telnyx + ClickSend |
| Reporting/ops | `Admin\FinanceCockpit\*`, `Admin\GatewayMonitor\*`, `Admin\PaymentHealth\*`, `Admin\AuthorizeNetRecurringHealthController`, `Admin\AuthorizeNetReportController`, `Admin\WebhookReportController`, `Admin\SalesTvController` | |
| Real Estate sub-product | `RealEstate\AgentAuthController`, `RealEstate\CompanyController`, `RealEstate\OrderController`, `RealEstate\SettingsController` | Separate `agent` guard, `routes/agent.php` |
| AI tooling | `AlControllers\AiChatController`, `ProjectAgent\ProjectAgentController`, `Services\ProjectAgent\ProjectAgentService`, `Services\ProjectAgent\ProjectScannerService`, `Services\LeadWelcomeAgentService`, `Services\OpenAIService` | `AlControllers\OldAiChatController` and `Services\OldOpenAIService` are legacy |
| Backups | `Console\Commands\BackupDatabase`, `Console\Commands\BackupProjectFiles`, spatie/laravel-backup, custom `Storage::extend('google', ...)` driver in `AppServiceProvider` | |

---

## 4. User Types & Guards

Defined in `config/auth.php:38-91`.

| Guard | Model | Table (inferred from model) | Middleware | Login entry point |
|---|---|---|---|---|
| `web` | `App\Models\User` | `users` | `App\Http\Middleware\Admin` — requires `Auth::user()->type == 1` | `admin.login.index` |
| `user` | `App\Models\Client` | `clients` | `App\Http\Middleware\User` — requires `Auth::guard('user')->check()` | `login.user` (`App\Http\Controllers\Web\LoginController`) |
| `agent` | `App\Models\RealEstate\Agent` | (Real Estate schema) | `App\Http\Middleware\AgentAuth` — requires `Auth::guard('agent')->check()` | `/agent/login` |

- `Client` uses `Laravel\Cashier\Billable` **and** has Authorize.Net CIM
  fields (`authorize_account_id`, card profile relation) — both billing
  systems coexist on the same model (see §26 technical debt).
- No separate DB table/guard for "salesman" — salesmen are `User` rows with
  specific `Role` permissions (e.g. permission string `Salesman`).

---

## 5. Roles / Permissions

- `App\Models\Role` (`app/Models/Role.php`) — `users()` hasMany `User` via
  `role_id`. `permissions` column is a **JSON array of permission strings**.
- Enforcement middleware: `App\Http\Middleware\CheckPermission`
  (registered as `checkpermission` in `app/Http/Kernel.php:70`).
  ```php
  if (Auth::guard('web')->check() && is_null(Auth::guard('web')->user()->role_id)) {
      return $next($request); // role_id === null => superadmin bypass
  }
  $userPermissions = json_decode($admin->role->permissions, true);
  foreach ($permissions as $permission) {
      if (in_array($permission, $userPermissions)) return $next($request);
  }
  // else: flash 'Permission denied...' and redirect to admin.dashboard
  ```
- **Important convention**: `role_id === null` on a `User` means unrestricted
  admin access. This bypass is duplicated independently in
  `App\Policies\FailedPaymentCasePolicy::hasPermission()`. Never assume a
  `role_id` is required for "admin" — a null role is more privileged, not less.
- Almost every route in `routes/admin.php` is gated with
  `->middleware('checkpermission:<PermissionName>')`, e.g. `Customers`,
  `Claims`, `Salesman`, `Technicians`, `Role`, `Lead Blaster`, `Calendar`,
  `Map`, `Payment failed(VB)`, `Renewal(VB)`.
- Failed Payment permission strings (used by both routes and the policy):
  `failed-payments.view`, `failed-payments.view-details`,
  `failed-payments.settings`, `failed-payments.export`,
  `failed-payments.reports`, `failed-payments.assign`,
  `failed-payments.update`, `failed-payments.notes`,
  `failed-payments.communications`, `failed-payments.retry-control`,
  `failed-payments.dry-run`.

---

## 6. Important Routes

### Public / lead intake (`routes/web.php`)
- `POST /first/entrance` … `POST /nineth-entrance` (`Admin\WebhookController`,
  `Admin\SecondWebhookController` … `Admin\NinethEntranceController`) — inbound lead webhooks.
- `POST /vanilasoft/user/info`, `POST /check/salesman/id`,
  `POST /vanilasoft/viewboard/info` (`VanilasoftController`) — dialer integration.
- `POST /telynx/webhook` (`Admin\TelynxController@telynxWebhook`).
- `GET /login/to/portal/{id}` — admin-initiated login into a customer's portal.

### Client portal (`routes/web.php`, prefix `client`, middleware `user`)
- `GET /client/dashboard`, `/client/package/details`, `/client/claim/*`,
  `/client/profile/*`, `/client/agreement/policy/*`.

### Payments (`routes/web.php`)
- `POST /clients/payment/make` → `Payment\AuthorizeNetPaymentController@authorizeNetSubscriptionPayment`
- `POST /clients/installment/payment/make` → `@authorizeNetInstallmentPayment`
- `POST /claim/payment/make` → `@authorizeNetClaimPayment`
- `GET /payment/success/{token}` → `@authorizeNetPaymentSuccess`
- `POST /clients/cancel/policy/refund/payment` → `@authorizeNetSubscriptionCancelPayment`
- `POST /clientinstallment/update/payment/make`, `/clientpayment/update/make` → `@authorizeNetSubscriptionUpdatePayment`
- `GET /payment/{claim_id}/{amount}/{client_id}/{token}` → `Admin\ClaimController@claimPaymentPageLink`
- `GET /payment/{client_id}/{amount}/{token}` → `Admin\ClientsController@clientPaymentPageLink`

### Authorize.Net webhooks (`routes/api.php`) — **unauthenticated, signature-verified**
- `POST /api/transaction/create/notify` → `Admin\TransactionCheckController@transactionCreateNotify`
- `POST /api/transaction/suspend/notify` → `@transactionSuspendNotify`
- `POST /api/transaction/terminated/notify` → `@transactionTerminatedNotify`

### Failed Payment Module (`routes/failed_payments.php`, prefix `failed-payments`)
- `GET /` → `Admin\FailedPaymentController@index`
- `GET /{failedPaymentCase}` → `@show` (route model binding on `case_uuid`, see §10)
- `POST /process-retries` → `@processRetries` (permission `failed-payments.retry-control`)
- `GET /export` → `@export`
- `GET /reports`, `GET /reports/export` → `Admin\FailedPaymentReportController`
- `POST /{failedPaymentCase}/assignment` → `Admin\FailedPaymentWorkflowController@updateAssignment`
- `POST /{failedPaymentCase}/status` → `@transitionStatus`
- `POST/PUT/DELETE /{failedPaymentCase}/notes[/{failedPaymentNote}]` → `@storeNote` / `@updateNote` / `@destroyNote`
- `POST /{failedPaymentCase}/communications/{preview,simulate,send}` → throttled `10,1`
- `POST /{failedPaymentCase}/retry/{failedPaymentRetrySchedule}/control` → `@controlRetry`
- `POST /{failedPaymentCase}/retry/{failedPaymentRetrySchedule}/simulate` → `@simulateRetry`, throttled `10,1`
- `GET/POST /settings` → `Admin\FailedPaymentSettingsController` (permission `failed-payments.settings`), all routes here also run middleware `failed-payments.settings.sync` (`App\Http\Middleware\SyncFailedPaymentSettings`)

### Admin panel (`routes/admin.php`, prefix `admin`, middleware `admin`)
845 lines, ~150 routes. Organized by prefix: `blogs`, `authorize-net-reports`,
`lead-blaster`, `entrance`, `main_page`, `lead_view`, `client_viewboard`,
`client_renewviewboard`, `clients`, `technicians`, `claim`, `sms`, `sales/man`,
`role`, `rules`, `shop`, `webhookreport`, `salesmanactioneport`. Every group
uses `checkpermission:<Name>`.

---

## 7. Controllers (surface map, not exhaustive)

Directory: `app/Http/Controllers/`
- `Admin/` — ~150 controllers, one per admin feature (see §3 table for the
  ones directly inspected).
- `Auth/` — Breeze-generated (`AuthenticatedSessionController`,
  `PasswordResetLinkController`, `NewPasswordController`,
  `RegisteredUserController`, `VerifyEmailController`, etc.) — admin (`web` guard) auth only.
- `Clint/` — customer portal.
- `Payment/` — `AuthorizeNetPaymentController` (thin wrapper, see §11),
  `StripePaymentController` (the actual charge-orchestration class despite the name).
- `RealEstate/` — agent portal.
- `Rule/` — lead routing configuration.
- `Web/` — public site controllers, including a **second**
  `StripePaymentController` at `app/Http/Controllers/Web/StripePaymentController.php`
  (1135 lines) — distinct class from `Payment\StripePaymentController` (1199
  lines); **do not confuse the two** when searching by class name alone.

---

## 8. Services

Directory: `app/Services/`

Core payment services (directly read):
- `AuthorizeNetArbService` — creates ARB subscriptions from an existing CIM
  profile (`createSubscriptionFromCustomerProfile`), and rebuilds a
  subscription for a recovered failed payment
  (`createRecoveredPaymentSubscription`, used by the Failed Payment recovery
  flow). Resolves the client's saved card via `resolveCardProfile()`.
- `AnetCimService` — creates Authorize.Net CIM customer + payment profiles
  from a raw card (`createCustomerProfileFromCard`), detects card brand.
- `AuthorizeNetRecurringHealthService` — powers `anet:reconcile-recurring-health`.
- `AuthorizeNetReportService` — powers `authorize-net-reports` admin pages.
- `WebhookService`, `ClaimIntakeWebhook`, `ClientInformationWebhookService` —
  outbound notification webhooks (e.g. to Zapier/Replit) fired after payment
  success/failure (`notifyPaymentOutcome` in `TransactionCheckController`).
- `TelnyxMessageService`, `VanilasoftClickToCallService` — telephony.
- `CampaignLeadCsvImportService` — backs `ImportCampaignLeadCsv` /
  `ProcessCampaignLeadImports`.
- `AnalyticsSyncService` — backs `BootstrapAnalyticsMirror` /
  `BackfillAnalyticsMirror` commands and the `Analytics*` model mirror tables.
- `OpenAIService`, `LeadWelcomeAgentService` — AI features.
  `OldOpenAIService` is legacy/unreferenced.
- `BuyNumberStateResolver` — used by `Admin\BuyNumberCsvUploadController`.

Failed Payment service layer (`app/Services/FailedPayment/`) — the best-engineered
part of the codebase, interface-driven:
- `Contracts/FailedPaymentRetryGatewayInterface`,
  `Contracts/FailedPaymentCommunicationGatewayInterface` — bound in
  `AppServiceProvider::register()` to dry-run or "unavailable" implementations
  based purely on config, never hard-coded per call site.
- `FailedPaymentCaptureService` — passive intake from webhook handlers (§13).
- `FailedPaymentRetryEligibilityService` — decides if a schedule can fire.
- `FailedPaymentRetryProcessorService` — executes a retry (simulation or live).
- `FailedPaymentRetryScheduleService` — seeds `FailedPaymentRetrySchedule` rows.
- `FailedPaymentRecoveryService` — `recoverFromSuccessfulRetry()`, calls
  `AuthorizeNetArbService::createRecoveredPaymentSubscription`.
- `FailedPaymentWebhookService` — `recordReceived()`, sanitized webhook audit capture.
- `FailedPaymentAdminWorkflowService` — backs `FailedPaymentWorkflowController`.
- `FailedPaymentHealthService`, `FailedPaymentReportService` — dashboards/reports.
- `FailedPaymentSettingsService` — `syncConfig()`, called on every request
  boot (`AppServiceProvider::boot()`) to overlay `FailedPaymentSetting` DB
  rows onto `config('failed_payments.*')`.
- `FailedPaymentFailureCategoryMapper` — maps gateway codes/reasons to a
  normalized failure category, and reports which categories are retryable.
- `FailedPaymentDemoEnvironmentGuard`, `FailedPaymentDemoFixtureService` —
  guard rails for the `failed-payments:demo-seed` / `demo-clear` commands so
  demo data can never be seeded/cleared outside a safe environment.
- Gateway implementations: `DryRunFailedPaymentRetryGateway`,
  `LiveFailedPaymentRetryGateway`, `FakeFailedPaymentRetryGateway`,
  `UnavailableFailedPaymentRetryGateway`, and the communication equivalents
  `DryRunFailedPaymentCommunicationGateway` / `UnavailableFailedPaymentCommunicationGateway`.

---

## 9. Models (selected, from `app/Models/`)

Core: `User`, `Client`, `Role`, `AuthorizeAccount`, `CardProfile`,
`PaymentHistory`, `PaymentCard`, `Claim`, `ClaimTrack`, `ClaimFile`,
`ClaimClosedDetail`, `LeadDetail`, `LeadStatus`, `Notes`, `Package`,
`PackageService`, `Promotion`, `RenewalClient`, `RenewAgrementPdf`,
`ClientCancelInfo`, `ClientRenewStatus`, `ClientRenewButtonTrack`,
`ClientTrackAction`, `AnetWebhookEvent`.

Failed Payment Module (all new): `FailedPaymentCase`, `FailedPaymentAttempt`,
`FailedPaymentAction`, `FailedPaymentStatusHistory`,
`FailedPaymentRetrySchedule`, `FailedPaymentWebhookEvent`,
`FailedPaymentCommunication`, `FailedPaymentCommunicationTemplate`,
`FailedPaymentNote`, `FailedPaymentAuditLog`, `FailedPaymentSetting`.

Analytics mirror (read-side, populated by `AnalyticsSyncService`):
`AnalyticsClaim`, `AnalyticsCustomer`, `AnalyticsLead`, `AnalyticsPayment`,
`AnalyticsSalesEvent`.

Authorize.Net recurring-health tooling: `AnetRecurringHealthGap`,
`AnetRecurringHealthRun`, `AnetRecurringHealthSnapshot`.

Real Estate sub-app: `app/Models/RealEstate/*` (separate namespace, not
individually inventoried here).

---

## 10. Database Tables & Relationships

### Core client/billing chain
```
users (User)  — role_id → roles.id
clients (Client) — guard 'user', Billable (Cashier) + CIM fields
  └─ card_profiles (CardProfile) — client_id, authorize_account_id,
       customer_profile_id, payment_profile_id
  └─ payment_history (PaymentHistory) — client_id, subscription_id,
       authorize_account_id, amount, status, payment_status, payment_type,
       claim_payment, is_custom, claim_id
       └─ payment_cards (PaymentCard) — payment_histroy_id (sic, real column name)
  └─ claims (Claim) — client_id
       └─ claim_track, claim_files, claim_closed_details
  └─ notes (Notes) — client_id
  └─ renew_agrement_pdfs (RenewAgrementPdf) — client_id
authorize_accounts (AuthorizeAccount) — encrypted casts on
  api_login_id, transaction_key, public_client_key, signature_key
anet_webhook_events (AnetWebhookEvent) — event_id unique, dedupe store
anet_webhook_event_reads — read-tracking on webhook events
```

### Failed Payment Module (migrations `2026_07_29_*` → `2026_08_13_*`)
```
failed_payment_cases (FailedPaymentCase)  — PK id, route key = case_uuid (UUID)
  ├─ failed_payment_attempts     — idempotency_key (unique per attempt)
  ├─ failed_payment_actions      — system/communication actions taken
  ├─ failed_payment_status_histories — full status audit trail
  ├─ failed_payment_retry_schedules  — sequence_number, scheduled_at, status
  ├─ failed_payment_webhook_events   — sanitized capture, separate from anet_webhook_events
  ├─ failed_payment_communications   — + failed_payment_communication_templates
  ├─ failed_payment_notes
  └─ failed_payment_audit_logs       — action + reason_code on every mutation
failed_payment_settings — DB override of config/failed_payments.php,
  synced by App\Http\Middleware\SyncFailedPaymentSettings and on every
  service-provider boot via FailedPaymentSettingsService::syncConfig()
```

`FailedPaymentCase` belongsTo: `Client` (`withDefault()`), `PaymentHistory`,
`AuthorizeAccount`, `AnetWebhookEvent` (via `anet_webhook_event_id` →
`event_id`), `User` as `assignedUser` (`withDefault()`), `Claim`.

### Client tracking/analytics side tables
`client_button_tracks`, `client_cancel_infos`, `client_login_infos`,
`client_renew_button_tracks`, `client_renew_statuses`,
`client_renew_track_actions`, `client_statuses`, `client_track_actions`,
`client_policies`, `client_earnings`, `renewal_clients`.

Total migration count observed: **160**. Only the tables above were directly
inspected; treat any table not listed here as unverified until read.

---

## 11. Payment Architecture (overview)

Two payment gateways are wired into the app simultaneously:

1. **Authorize.Net (CIM + ARB)** — the live, actively-used recurring billing
   engine. Merchant credentials live per-row in `authorize_accounts`
   (multiple merchant accounts supported, `is_active` flag, weighted
   selection via `weight` column).
2. **Stripe / Laravel Cashier** — `Client` uses the `Billable` trait and
   `STRIPE_KEY`/`STRIPE_SECRET` are configured, but the traced checkout path
   (`Payment\AuthorizeNetPaymentController` → `Payment\StripePaymentController`
   → `Admin\SubscriptionController`) resolves to Authorize.Net, not Stripe's
   API. `Payment\StripePaymentController` is a **legacy-named class that now
   drives Authorize.Net checkout** — do not assume "Stripe" in a class name
   means the Stripe gateway is used.

Entry point naming is confusing by design (technical debt, not a bug per se):
```php
// app/Http/Controllers/Payment/AuthorizeNetPaymentController.php
class AuthorizeNetPaymentController extends StripePaymentController
{
    public function authorizeNetSubscriptionPayment(Request $request)
    {
        return parent::stripeSubscriptionPayment($request); // <- actually Authorize.Net
    }
    // ...same pattern for installment/claim/cancel/update payment methods
}
```

---

## 12. Authorize.Net / CIM / ARB Flow

**Profile + subscription creation** (charge orchestration):
- `Payment\StripePaymentController::stripeSubscriptionPayment()`
  (`app/Http/Controllers/Payment/StripePaymentController.php:339`) branches
  on `Client.payment_structure` (`1`=monthly, `2`=yearly with optional
  `installment`) and delegates to `Admin\SubscriptionController`:
  - `monthlySubcriptonTransaction()`
  - `installmentSubcriptonTransaction()`
  - `yearlySubscriptionPayment()`
  (These methods were **not directly read** in this session — treat
  `Admin\SubscriptionController` as the real CIM/ARB charge engine and read
  it before modifying checkout behavior.)
- `App\Services\AnetCimService::createCustomerProfileFromCard()` — builds
  the Authorize.Net `CreateCustomerProfileRequest` (customer profile +
  payment profile + billing address), returns
  `['customer_profile_id' => ..., 'payment_profile_id' => ...]`.
- `App\Services\AuthorizeNetArbService::createSubscriptionFromCustomerProfile()`
  — builds an `ARBCreateSubscriptionRequest` from an **existing CIM profile
  only** (explicitly does not send raw `BillTo` — comment in source:
  "Do NOT send BillTo here"). Returns the Authorize.Net subscription id.
- `AuthorizeNetArbService::createRecoveredPaymentSubscription()` — used only
  by the Failed Payment recovery flow to re-subscribe a client using their
  existing saved `CardProfile` after a successful manual/live retry.
- `AuthorizeNetArbService::resolveCardProfile()` — picks the client's CIM
  card, preferring the account tied to the payment/authorize account context,
  falling back to the client's most recent `CardProfile` (`orderByDesc('is_default')`).
- Cancellation: `Admin\TransactionCheckController::cancelSubscriptionOnGateway()`
  builds an `ARBCancelSubscriptionRequest`.

**Environment/credential handling**: `AuthorizeAccount.environment` is
`'sandbox'` or `'production'`; `api_login_id`/`transaction_key`/
`public_client_key`/`signature_key` are stored with Eloquent `encrypted`
casts. Decryption helpers (`decryptMaybe()` in `AuthorizeNetArbService`,
`decryptIfNeeded()` in `TransactionCheckController`) **silently fall back to
the raw stored value if `Crypt::decryptString()` throws** — this masks
corrupted/legacy-plaintext credentials rather than failing loudly (technical
debt / risk, see §29).

---

## 13. Failed-Payment Flow

Fully covered by `app/Services/FailedPayment/*` (§8) and
`app/Models/FailedPaymentCase.php` + related models (§10). Summary of the
runtime flow:

1. **Trigger**: Authorize.Net webhook lands on
   `Admin\TransactionCheckController@transactionSuspendNotify` (or
   `transactionTerminatedNotify`) → normal `PaymentHistory`/`Client.status`
   mutation happens exactly as before the module existed (unchanged, see §16) →
   **then, additionally**:
   ```php
   $this->capturePassiveFailedPayment([...]);  // wraps FailedPaymentCaptureService::capture()
   ```
2. **Gate**: `FailedPaymentCaptureService::capture()` immediately returns
   `null` unless `config('failed_payments.module_enabled')` **and**
   `config('failed_payments.capture_enabled')` are both true. Both default
   to `false` in `.env.example` — the module is inert unless explicitly
   turned on.
3. **Case resolution/creation** (inside `DB::transaction`):
   - Looks up an existing case by `anet_webhook_event_id` first, then by a
     **SHA-256 `case_key`** hashed from client/account/payment-history/
     subscription/tx-id/amount/currency/failure-code/frequency
     (`FailedPaymentCaptureService::caseKey()`), with `lockForUpdate()`.
   - If none found: `createCase()` — status `NEW` if client/payment-history
     is known, else `UNMATCHED`.
   - If found: `mergeCaseSnapshot()` — only fills empty fields, never
     overwrites populated ones; promotes `UNMATCHED → OPEN` once a client
     link is discovered later.
4. **Attempt recording**: `recordAttempt()` — deduped by its own SHA-256
   `attemptIdempotencyKey()` (case key + event id + payment-history id +
   tx id + attempt type/source + amount + failure code). Duplicate webhook
   deliveries create **zero** additional rows.
5. **Sanitization**: `sanitizeContext()` strips `full_card_number`, `cvv`,
   `signature_key`, `transaction_key`, `api_login_id`, and raw
   payload blobs from anything persisted to `metadata`.
6. **Retry scheduling**: `FailedPaymentRetryScheduleService::createForCaseResult()`
   seeds `FailedPaymentRetrySchedule` rows; failure here is caught and logged
   as a `retry_schedule_seed_failed` audit entry — never blocks the capture.
7. **Retry execution**: `FailedPaymentRetryProcessorService::processSchedule()`
   — row-locks the schedule, checks `FailedPaymentRetryEligibilityService`,
   then branches on `execution_intent` (`simulation` | `manual` | `automatic`):
   - **Simulation** always allowed if `config('failed_payments.dry_run_mode')`
     is true; deduped via a hashed `simulation_key` (schedule + sequence +
     scheduled_at + first_failed_at + timezone + intent) so repeat clicks
     don't re-log.
   - **Manual/automatic (live)** only proceeds if
     `retry_processing_mode === 'live'` **and** `retry_gateway_driver === 'live_cim'`
     — otherwise returns a `blocked`/`gateway_unavailable` result. Both
     config values default to `dry_run`.
   - On live success: `FailedPaymentRecoveryService::recoverFromSuccessfulRetry()`
     → new ARB subscription via `AuthorizeNetArbService`.
8. **Everything is audited**: every branch calls `recordAudit()` →
   `FailedPaymentAuditLog` row with `action`, `reason_code`, `safe_reason`,
   sanitized metadata.
9. **Admin surface**: `Admin\FailedPaymentController` (list/show/export/
   process-retries), `Admin\FailedPaymentWorkflowController` (assignment,
   status transitions, notes, communications preview/simulate/send, retry
   control/simulate), `Admin\FailedPaymentReportController`,
   `Admin\FailedPaymentSettingsController`. All gated by
   `checkpermission:failed-payments.*` + `FailedPaymentCasePolicy`.

**Currently-off-by-default flags** (`config/failed_payments.php`, all `env()`-driven):
`module_enabled`, `capture_enabled`, `admin_enabled`,
`automatic_retry_enabled`, `manual_retry_enabled`, `email_enabled`,
`sms_enabled`, `voice_enabled`, `voicemail_enabled` — all `false`.
`retry_gateway_driver` / `retry_processing_mode` — both `dry_run`.

---

## 14. Recurring Billing Flow

Billing is **schedule-driven on Authorize.Net's side** (ARB), not by a local
cron loop that charges cards. This app's role is to:
1. Create/update ARB subscriptions when a client checks out or their plan changes.
2. **React** to Authorize.Net's webhook callbacks for created/suspended/terminated
   transactions (`transactionCreateNotify` / `transactionSuspendNotify` /
   `transactionTerminatedNotify` in `Admin\TransactionCheckController`).
3. Backfill any `PaymentHistory` rows Authorize.Net's webhook implies should
   exist but are missing (`createMissingMonthlyPayments()` — see §17 duplicate protection).
4. Feed the Failed Payment Module on suspend/terminate events (§13).

Related scheduled command: `anet:reconcile-recurring-health --chunk=200`
(daily 01:30) — backed by `AuthorizeNetRecurringHealthService`, writes to
`AnetRecurringHealthRun`/`Snapshot`/`Gap` — a **reconciliation/audit** pass,
separate from the Failed Payment Module, that appears to check for drift
between local records and Authorize.Net's actual subscription state.

---

## 15. Webhook Flow

### Inbound (this app receives)
- **Authorize.Net billing events** — `routes/api.php` → `Admin\TransactionCheckController`.
  Verified via `x-anet-signature: sha512=...` HMAC header, matched against
  **every active `AuthorizeAccount`'s signature key**
  (`detectAccountFromWebhook()`), because multiple merchant accounts share
  the same public callback URLs.
- **Lead source webhooks** — `routes/web.php`: `/first-entrance` through
  `/nineth-entrance`, `/zero-entrance`, `/mm-webhook`, `/avchad-webhook`,
  `/new/yair-webhook/link`, `/campaign/webhook`, `/cel/webhook`,
  `/quote/email/webhook`, `/pquote/email/webhook`.
- **Vanilasoft dialer** — `/vanilasoft/user/info`, `/vanilasoft/viewboard/info`.
- **Telephony** — `/telynx/webhook`, `/telnyx/salesman-messages/webhook`, `/clicksend/get/notification`.
- **Email service** — `/new/email/webhook`, `/second/new/webhook`, `/change/email/webhook`.
- **Replit integration** — `/replit/claim/status` → `Replit\ReplitWebhookController@claimStatus`.

### Outbound (this app sends)
- `App\Services\ClaimIntakeWebhook` — `sendPaymentResolved()`,
  `sendPaymentZapierResolvedClean()`, `sendPaymentZapierFailedClean()`,
  `sendPaymentFailed()` — fired from
  `TransactionCheckController::notifyPaymentOutcome()` after every
  webhook-driven payment success/failure.
- `App\Services\ClientInformationWebhookService` — fired from
  `StripePaymentController::sendMakePaymentWebhookAfterSuccessfulAuthorizePayment()`
  to `env('REPLIT_SALE_MAKE_PAYMENT_WEBHOOK_URL')`.

### Design pattern to preserve
Every Authorize.Net webhook handler wraps its whole body in
`try { ... } catch (\Throwable $e) { Log::error(...); }` and **always
returns HTTP 200 `{"status":"success"}`**, even on internal failure — this is
intentional, to stop Authorize.Net from retry-storming a broken endpoint.
Do not "fix" this into a non-200 error response without understanding this
tradeoff; instead rely on the `Log::error`/`Log::warning` calls for visibility.

---

## 16. Cron / Scheduler Flow

`app/Console/Kernel.php::schedule()`:

| Schedule | Command | Class |
|---|---|---|
| everyMinute | `check:subscription-status` | `CheckSubscritionStatus` |
| daily | `renewal:client` | `RenewalClients` |
| daily | `claim-payment:check` | `ClaimPaymentFailed` |
| everyFiveMinutes | `renewal:client-delete` | `RenewalClientsDelete` |
| daily | `replicate:payment-faild-row` | `ReplicateMonthlyFailedPayments` |
| everyFiveMinutes, `withoutOverlapping()` | `failed-payments:process-retries` | `FailedPaymentsProcessRetries` — **only runs `when()` `retry_processing_mode === 'live'`** |
| everyFiveMinutes, `withoutOverlapping()` | `failed-payments:process-communications` | `FailedPaymentsProcessCommunications` — `when()` communication rules configured |
| dailyAt('01:30'), `withoutOverlapping()` | `anet:reconcile-recurring-health --chunk=200` | `CheckAuthorizeNetRecurringHealth` |
| dailyAt('02:00') | `backup:run` | spatie/laravel-backup |
| dailyAt('02:10') | `backup:clean` | spatie/laravel-backup |
| everyMinute, `withoutOverlapping()` | `campaign:process-lead-imports --limit=1` | `ProcessCampaignLeadImports` |

Registered-but-not-scheduled commands (in `$commands` array, run manually or
via other triggers): `CalculateClientEarnings`, `BootstrapAnalyticsMirror`,
`BackfillAnalyticsMirror`, `ImportCimTxtToCardProfiles`,
`RestoreLeadDetailsFromSql`, `Foroldsystem`,
`FailedPaymentsBackfillRetrySchedules`, `BackupDatabase`.

**Commented out / disabled** in `Kernel::$commands`:
`FailedPaymentsHealthCommand`, `FailedPaymentsDemoSeed`,
`FailedPaymentsDemoClear`, `FailedPaymentsReconcile`,
`SplitLeadsByCreatedDate`, `GoogleDriveRefreshToken`,
`ImportCampaignLeadCsv` (superseded by `ProcessCampaignLeadImports`).
`CheckReminders` is commented out at both the schedule line and the
commands array.

**No queued jobs** — every command above runs synchronously when cron fires it.

---

## 17. Jobs / Events / Listeners

- **No `app/Jobs/` directory exists.** There is no ShouldQueue job class
  anywhere in the app as of this analysis.
- Events (`app/Events/`): `LeadCreated`, `BlogEvent`, `PasswordFailedThreeTimes`,
  `RemainderEvent`, `VanilasofEvent`, `WebhookEvent`.
- Listeners (`app/Listeners/`, wired in `App\Providers\EventServiceProvider`):
  - `LeadCreated` → `SendLeadWelcomeEmail`
  - `BlogEvent` → `NotifyBlogByUsers`
  - `Illuminate\Auth\Events\Registered` → `SendEmailVerificationNotification` (framework default)
  - `EventServiceProvider::shouldDiscoverEvents()` returns `false` — **event
    auto-discovery is disabled**; any new event/listener pair must be
    registered explicitly in `$listen`, or it will silently never fire.
- Mail: `App\Mail\NewLeadWelcomeMail` (only Mailable class in the app).

---

## 18. Email / SMS Flow

- **Email**: `Illuminate\Support\Facades\Mail` used directly from
  controllers in most places (e.g. claim payment email in
  `StripePaymentController::stripeSinglePayment()` via
  `Mail::send('emails.claim_email', ...)`), plus the one formal `Mailable`
  (`NewLeadWelcomeMail`) triggered by the `LeadCreated` event.
- **SMS/Voice**: `twilio/sdk` package (Twilio SMS/voice), plus a custom
  `App\Services\TelnyxMessageService` for Telnyx SMS and
  `Admin\SalesmanMessageController` (Telnyx webhook receiver for salesman
  messaging). `Admin\ClickSendController` for ClickSend notifications.
  `Admin\VoiceController`, `Admin\VoicemailController`, `Admin\VoiceSignatureController`.
- **Failed Payment communications**: gated entirely behind
  `FailedPaymentCommunicationGatewayInterface`, defaulting to
  `DryRunFailedPaymentCommunicationGateway` (simulated, never actually sent)
  unless `communication_simulation_enabled` is explicitly false, in which
  case it resolves to `UnavailableFailedPaymentCommunicationGateway` (hard
  refuses to send) — there is currently **no live send gateway wired into
  the container** for this module.

---

## 19. Third-Party Services

| Service | Config location | Purpose |
|---|---|---|
| Authorize.Net | `authorize_accounts` DB table (encrypted) + `config('services.authorizenet')` | Core CIM/ARB billing |
| Stripe (`laravel/cashier`) | `config('services.stripe')`, `STRIPE_KEY`/`STRIPE_SECRET` | Present on `Client` but not confirmed as the active charge path |
| Twilio | `ACCOUNT_SID` / `AUTH_TOKEN` / `TWILIO_PHONE_NUMBER` | SMS/voice |
| Telnyx | `config('services.telnyx')` | SMS, salesman messaging webhook |
| ClickSend | webhook endpoint only | Lead/marketing notification |
| Vanilasoft | multiple webhook endpoints | Outbound dialer + salesman attribution |
| Constant Contact | `/constant/contact/notification` | Marketing |
| OpenAI | `config('services.openai')`, `AI_PROJECT_AGENT_*` | AI chatbot + project agent |
| Google Drive | custom `Storage::extend('google', ...)` in `AppServiceProvider` | Backup destination |
| Google reCAPTCHA | `google/recaptcha` package | Form protection (specific forms not traced) |
| AWS | `AWS_*` env vars | S3-style storage, bucket blank in `.env.example` — usage unconfirmed |
| Pusher | `pusher-php-server` + broadcasting config | Real-time broadcast (`channels.php`, `VanilasofEvent`/`WebhookEvent` test route at `/test-broadcast`) |

No secret values from `.env` were read or should ever be pasted into this
file — reference variable **names** only.

---

## 20. Critical Business Rules

- **`role_id === null` = superadmin** — bypasses all `checkpermission`
  middleware and `FailedPaymentCasePolicy` checks. Never treat a null role
  as "no access."
- **Payment structure drives billing cadence**: `Client.payment_structure`
  `'1'` = monthly, `'2'` = yearly (optionally with `Client.installment` set
  for installment billing) — this branches which `Admin\SubscriptionController`
  method runs and which webhook-driven backfill logic applies.
- **`PaymentHistory` "eligible policy" scoping**: several `Client` relations
  filter out claim payments and custom payments when reasoning about policy
  billing state:
  ```php
  ->where('claim_payment', 0)->where('is_custom', 0)
  ```
  (`Client::latestEligiblePolicyPaymentHistory()`,
  `latestFailedPolicyPaymentHistory()`, `latestPaidPolicyPaymentHistory()`).
  Any new payment-history query for "real" recurring billing should apply
  the same filters or it will double-count claim/one-off charges.
- **Void vs. refund cutoff**: `PaymentHistory::getCanVoidAttribute()` — a
  payment can be voided only until **9:05 PM on its creation date**
  (hardcoded `21:05:00` cutoff); after that, `getCanRefundAttribute()`
  (`= !can_void`) governs — i.e., same-day cancellations void, later ones refund.
- **Failed Payment Module is additive-only**: it must never mutate
  `PaymentHistory` or `Client.status` — those are owned exclusively by the
  original webhook handlers in `TransactionCheckController`. This is an
  explicit design invariant (see `docs/failed-payment-module-testing.md`:
  "No change to PaymentHistory or client status").

---

## 21. Duplicate-Payment / Duplicate-Record Protections

- **Webhook-level idempotency**: `AnetWebhookEvent.event_id` is unique;
  `webhookEventExists()` is checked **before** any business logic runs in
  every `transaction*Notify()` handler — a re-delivered Authorize.Net webhook
  is a pure no-op after the first successful processing.
- **Signature verification** (`detectAccountFromWebhook()`) prevents forged
  webhook calls from creating/mutating payment records at all.
- **Missing-payment backfill guard**: `createMissingMonthlyPayments()`
  explicitly queries for an existing row in the same billing month before
  inserting a replicated `PaymentHistory` row:
  ```php
  $existingMissingPayment = PaymentHistory::where('subscription_id', ...)
      ->where('claim_payment', 0)->where('is_custom', 0)
      ->where('payment_type', ...)
      ->whereRaw("DATE_FORMAT(payment_date, '%Y-%m') = ?", [$nextExpected->format('Y-m')])
      ->latest()->first();
  ```
- **Failed Payment case dedup**: SHA-256 `case_key` (see §13) ensures the
  same underlying failure is never represented by two `FailedPaymentCase` rows.
- **Failed Payment attempt dedup**: SHA-256 `attemptIdempotencyKey()` per
  attempt; **`FailedPaymentRetryProcessorService::applyLiveResult()` uses
  `updateOrCreate` keyed on `(failed_payment_case_id, attempt_number)`** so a
  retried live-retry call updates rather than duplicates the attempt row.
- **Retry simulation dedup**: hashed `simulation_key` prevents repeat
  "simulate retry" clicks from generating duplicate audit noise.
- **Row locking**: `lockForUpdate()` is used on `PaymentHistory` (in the
  suspend-notify transaction), on `FailedPaymentCase` (in `resolveCase()`),
  and on `FailedPaymentRetrySchedule` (in `processSchedule()`) — always
  inside `DB::transaction()`. **Any new payment-mutating code path should
  follow this same lock-inside-transaction pattern.**
- **Live retry hard gate**: a real (non-simulated) retry can only fire if
  **both** `retry_processing_mode === 'live'` and
  `retry_gateway_driver === 'live_cim'` are set — a single flag flip is not
  enough, reducing the chance of accidentally enabling live charges.

---

## 22. Important Status Values

- `Client.status` — `1` = active/good standing, `0` = set to failed/suspended
  by `transactionSuspendNotify()`. `Client.login_off == 1` blocks portal login
  entirely ("Your policy has been canceled").
- `PaymentHistory.status` / `PaymentHistory.payment_status` — `1` = success,
  `0` = failed/unpaid (both fields exist and are used somewhat
  interchangeably across the code — verify which one a given query means
  before writing new logic against it).
- `PaymentHistory.claim_payment` — `1` = this row is a claim/call payment,
  not a policy billing row; `PaymentHistory.is_custom` — `1` = manually
  entered/one-off payment, excluded from "eligible policy payment" scopes.
- `PaymentHistory.payment_type` — `1` implies bank/ACH in at least one branch
  (`$paymentH->payment_type === 1 ? 'bank' : 'card'` in
  `TransactionCheckController`); confirm the full enum before relying on other values.
- `AuthorizeAccount.is_active` — only active accounts are considered by
  `activeAuthorizeAccount()` / webhook signature matching.
- `FailedPaymentCaseStatus` enum (`App\Enums\FailedPaymentCaseStatus`):
  includes at least `NEW`, `OPEN`, `UNMATCHED` (confirmed in code); check the
  enum file directly for the full set before adding new transitions.
- `FailedPaymentRetryScheduleStatus` enum: `PENDING`, `COMPLETED`, `FAILED`,
  `SKIPPED` (confirmed via `FailedPaymentRetryProcessorService::applyLiveResult()`).
- `FailedPaymentRetryAttemptResult` enum: `SUCCESS`, `TEMPORARY_FAILURE`,
  `BLOCKED`, and a default/failed case.
- `FailedPaymentRetryExecutionIntent` enum: `SIMULATION`, `MANUAL`,
  `AUTOMATIC` (inferred from usage — normalize via
  `FailedPaymentRetryExecutionIntent::normalize()`).

---

## 23. Critical Files

| File | Why it matters |
|---|---|
| `app/Http/Controllers/Admin/TransactionCheckController.php` | Authorize.Net webhook receiver; owns `PaymentHistory`/`Client.status` mutation; 1612 lines |
| `app/Http/Controllers/Payment/StripePaymentController.php` | Actual Authorize.Net checkout orchestration despite the class name; 1199 lines |
| `app/Http/Controllers/Web/StripePaymentController.php` | A **separate** 1135-line class of the same short name in a different namespace — do not conflate |
| `app/Services/AuthorizeNetArbService.php` | ARB subscription create/recover |
| `app/Services/AnetCimService.php` | CIM customer/payment profile creation |
| `app/Models/Client.php` | Central customer model; payment-history scoping rules live here |
| `app/Models/PaymentHistory.php` | Void/refund cutoff logic, core billing ledger |
| `app/Models/AuthorizeAccount.php` | Encrypted merchant credentials, multi-account support |
| `app/Services/FailedPayment/FailedPaymentCaptureService.php` | Entry point for all passive failed-payment capture |
| `app/Services/FailedPayment/FailedPaymentRetryProcessorService.php` | Live/simulated retry execution gate |
| `app/Providers/AppServiceProvider.php` | Binds Failed Payment gateway interfaces; syncs `FailedPaymentSettingsService`; enables/disables model observers |
| `app/Http/Middleware/CheckPermission.php` | Permission enforcement + the `role_id === null` superadmin bypass |
| `app/Policies/FailedPaymentCasePolicy.php` | Only Eloquent policy in the app |
| `config/failed_payments.php` | Master feature-flag file for the whole module |
| `docs/failed-payment-module-testing.md` | Testing conventions for the module (isolated sqlite DB, no live gateway calls) |
| `routes/admin.php` | Entire admin route surface (845 lines) |
| `routes/api.php` | Authorize.Net webhook endpoints |
| `routes/failed_payments.php` | Failed Payment Module admin routes |

---

## 24. Critical Database Tables

`users`, `clients`, `authorize_accounts`, `card_profiles`, `payment_history`,
`payment_cards`, `anet_webhook_events`, `roles`, `claims`, `renewal_clients`,
and the entire `failed_payment_*` table family (§10). Any migration touching
these must be additive/backward-compatible — they are live production data
paths, not scaffolding.

`failed_payment_settings` deserves special note: it is read on **every
request** (`FailedPaymentSettingsService::syncConfig()` in
`AppServiceProvider::boot()`, wrapped in try/catch so a DB issue here
degrades to config defaults rather than crashing the app) and by the
`failed-payments.settings.sync` middleware on the settings routes.

---

## 25. Dangerous / Destructive Commands to Avoid

Per the analysis-only constraint that produced this document, and as a
standing rule for future sessions:

- **Never run** `migrate:fresh`, `migrate:refresh`, `db:wipe`,
  `Schema::dropAllTables()`, or raw destructive SQL against this database.
- **`renewal:client-delete`** (`RenewalClientsDelete`, scheduled every 5
  minutes) — name implies deletion of `renewal_clients` rows; read the
  command body before modifying its query, since it runs unattended and
  frequently.
- **`failed-payments:demo-seed` / `failed-payments:demo-clear`** — guarded by
  `FailedPaymentDemoEnvironmentGuard`/`FailedPaymentDemoFixtureService`
  specifically so they cannot run outside a safe/demo environment; do not
  loosen those guards.
- **Live Authorize.Net calls** — never invoke `AuthorizeNetArbService`,
  `AnetCimService`, or the Failed Payment `LiveFailedPaymentRetryGateway`
  against a real merchant account outside of an explicit, approved test.
  The whole Failed Payment retry system is designed so that flipping
  `retry_processing_mode=live` + `retry_gateway_driver=live_cim` is the only
  way to reach real money movement — leave both at their `dry_run` defaults
  unless specifically instructed otherwise.
- **`db-backup/download/{token}`** route in `web.php` streams the latest DB
  backup file to anyone with the right `BACKUP_DOWNLOAD_TOKEN` — treat that
  token with the same sensitivity as a DB credential.

---

## 26. Legacy Code vs. Active Code

**Legacy / superseded (not referenced by any route file read in this
session — treat as inactive, but verify before deleting):**
- `Admin\OldTransactionCheckController`
- `Admin\OldUnsuccessfullClientsPyamentController`
- `Admin\OldYAIRWebhookController`
- `AlControllers\OldAiChatController`
- `Services\OldOpenAIService`
- `Console\Commands\OldCheckSubscritionStatus`

**Active but with confusing naming (real functionality, not legacy):**
- `Payment\StripePaymentController` — drives Authorize.Net, not Stripe.
- `Payment\AuthorizeNetPaymentController` — thin wrapper delegating to the above.

**Commented-out but present in code (disabled, not deleted):**
- Model observers: `ClientObserver`, `PaymentHistoryObserver`, `ClaimObserver`
  (see §2).
- Console commands: `FailedPaymentsHealthCommand`, `FailedPaymentsDemoSeed`,
  `FailedPaymentsDemoClear`, `FailedPaymentsReconcile`,
  `SplitLeadsByCreatedDate`, `GoogleDriveRefreshToken`, `CheckReminders`.

**New / actively developed (this branch, `failed-payment-module`):** every
file under `app/Services/FailedPayment/`, `app/Models/FailedPayment*`,
`routes/failed_payments.php`, `Admin\FailedPayment*Controller`, and the
`tests/Feature/FailedPayment*Test.php` suite.

---

## 27. Known Technical Debt

- `Payment\StripePaymentController::stripeSinglePayment()` (claim/call
  payment, route `POST /claim/payment/make`) calls
  `$this->gateway->authorize(...)`, but `$this->gateway` is **only ever
  assigned inside a fully commented-out constructor block**
  (`StripePaymentController.php:44-50`). As written, this path would fail
  with a null-method-call error if invoked. **Verify live behavior before
  touching claim payments** — either this route is dead, or something else
  is compensating that was not found in this session.
- Dual billing systems on `Client` (Cashier/Stripe `Billable` trait + CIM
  fields) with only Authorize.Net confirmed as the live path — clarify
  intent before extending either.
- `decryptMaybe()` / `decryptIfNeeded()` silently return the raw value on
  decrypt failure instead of raising — could mask corrupted credentials.
- Confusing class naming: two different `StripePaymentController` classes in
  different namespaces, one of which is misleadingly named for what it does.
- Superadmin-via-null-role convention duplicated independently in two places
  (`CheckPermission` middleware and `FailedPaymentCasePolicy`) instead of a
  shared helper — a future third permission check is likely to reimplement
  it a third way (or forget it).
- `PaymentHistory.status` and `PaymentHistory.payment_status` both exist and
  appear to serve overlapping purposes — the distinction was not fully
  resolved in this session.
- Legacy client-login fallbacks are live security-relevant code, not fully
  removed: plaintext-password-compat upgrade path and policy-number-as-password
  fallback, both in `Web\LoginController::login()`.

---

## 28. Testing Architecture

- Standard PHPUnit (`phpunit.xml` default) plus a **dedicated**
  `phpunit.failed-payments.xml` that forces `APP_ENV=testing`,
  `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` **before Laravel
  bootstraps**, specifically so the Failed Payment test suite can never touch
  the real database (see `docs/failed-payment-module-testing.md`).
- `tests/Support/FailedPaymentBootstrapGuard.php` and
  `FailedPaymentTestDatabase.php` — custom scaffolding enforcing the sqlite
  isolation above.
- Failed Payment test files (`tests/Feature/`):
  `FailedPaymentAdminModuleTest`, `FailedPaymentBootstrapSafetyTest`,
  `FailedPaymentCaptureTest`, `FailedPaymentDemoFixtureCommandTest`,
  `FailedPaymentPhase2aFoundationTest`, `FailedPaymentRecoveryTest`,
  `FailedPaymentRetryEligibilityTest`, `FailedPaymentRetryProcessorTest`,
  `FailedPaymentRetryScheduleTest`, `FailedPaymentSchemaSafetyTest`,
  `FailedPaymentWebhookIntegrationTest`.
- Stock Breeze auth tests: `tests/Feature/Auth/*`, `ProfileTest`.
- `tests/Unit/AuthorizeNetRecurringHealthServiceTest.php` — the only unit
  test touching payment-adjacent logic outside the Failed Payment module.
- **No test coverage found** for: the core Authorize.Net charge/subscription
  flow (`Admin\SubscriptionController`, `AuthorizeNetArbService`,
  `AnetCimService`), the legacy claim-payment path flagged in §27, lead
  intake webhooks, claims workflow, client CRUD, permission middleware, or
  renewal commands. Any change in those areas has no regression safety net —
  proceed carefully and manually verify.

Suggested commands (from the docs file):
```
vendor/bin/phpunit -c phpunit.failed-payments.xml --filter FailedPayment
vendor/bin/phpunit -c phpunit.failed-payments.xml --filter FailedPaymentBootstrapSafety
php artisan route:list
php artisan schedule:list
```

---

## 29. High-Risk Areas

| Area | Risk | Reason |
|---|---|---|
| `Admin\TransactionCheckController` webhook handlers | **CRITICAL** | Unauthenticated endpoints; directly mutate `Client.status` and `PaymentHistory`; signature-matching bug would silently misprocess real payment events across merchant accounts |
| `createMissingMonthlyPayments()` backfill logic | **HIGH** | Date-gap heuristic generating `PaymentHistory` rows; duplicate guard exists but any edge case in month-boundary math risks double/missing billing records |
| Failed Payment live retry path | **HIGH** | Fully wired end-to-end but inert by config; two flags away from real charge retries — any future change here must preserve the existing idempotency-key/lock discipline |
| Client legacy auth fallbacks (`Web\LoginController`) | **HIGH** | Plaintext-password compatibility path + policy-number-as-password login are both live account-takeover-adjacent surfaces |
| `renewal:client-delete` (every 5 min) | **MEDIUM-HIGH** | Destructive-sounding, frequent, unattended — read fully before modifying `renewal_clients` schema or query |
| `role_id === null` superadmin bypass | **MEDIUM** | Convention-based and duplicated in two independently-maintained places; easy to accidentally grant full admin by leaving `role_id` null on a new `User` |
| `AuthorizeAccount` credential decrypt fallback | **MEDIUM** | Silent fallback to raw value on decrypt failure could mask a corrupted/misconfigured credential rather than failing loudly |
| Stripe/Authorize.Net dual-wiring on `Client` | **LOW-MEDIUM** | Ambiguity about which system is authoritative for a given client complicates any future billing-system change |

---

## 30. End-to-End Flow Diagrams

### Lead → Client → Subscription
```
Lead webhook (Admin\*EntranceController) / campaign CSV import
        ↓
LeadDetail (LeadDetailObserver) → Rule\* routing → Inquiry
        ↓ (Admin\InquiriesController::ConvertInquiryToClient)
Client record created
        ↓
PlanCheckoutController / ShopPlansController (zip, package, addon, price)
        ↓
Admin\SubscriptionController (monthly/installment/yearly transaction methods)
        ↓
AnetCimService::createCustomerProfileFromCard()  →  CardProfile row
        ↓
AuthorizeNetArbService::createSubscriptionFromCustomerProfile()
        ↓
Authorize.Net ARB Subscription (billing now lives on Authorize.Net's schedule)
```

### Authorize.Net webhook → Failed Payment case
```
Authorize.Net (async, on its own billing schedule)
        ↓  POST /api/transaction/suspend/notify  (x-anet-signature: sha512=...)
Admin\TransactionCheckController::transactionSuspendNotify()
        ↓ detectAccountFromWebhook() [signature match against all active AuthorizeAccount rows]
        ↓ webhookEventExists() dedupe check  → if duplicate: stop, return 200
        ↓ DB::transaction + PaymentHistory::lockForUpdate()
        ├─→ createMissingMonthlyPayments()      (backfill, duplicate-guarded)
        ├─→ createPaymentAttemptRowIfMissing()
        └─→ Client.status = 0, Client.payment_faild_date = eventDate
        ↓
notifyPaymentOutcome()  →  ClaimIntakeWebhook (external Zapier notifications)
        ↓
capturePassiveFailedPayment()  →  FailedPaymentCaptureService::capture()
        ↓ [no-op unless module_enabled + capture_enabled config flags are true]
        ↓ DB::transaction + FailedPaymentCase::lockForUpdate() by case_key/webhook_event_id
        ├─→ createCase() or mergeCaseSnapshot()
        ├─→ recordAttempt()          (deduped by attemptIdempotencyKey)
        ├─→ recordStatusHistory()    (on create / status change)
        └─→ recordSystemAction()
        ↓
FailedPaymentRetryScheduleService::createForCaseResult()  →  FailedPaymentRetrySchedule rows
        ↓ (later, via admin action or failed-payments:process-retries cron — only when retry_processing_mode=live)
FailedPaymentRetryProcessorService::processSchedule()
        ↓ eligibility check → simulation (dry-run, default) OR live retry (gated, both flags required)
        ├─ simulation → DryRunFailedPaymentRetryGateway → audit log only, no gateway call
        └─ live → LiveFailedPaymentRetryGateway → on success →
                  FailedPaymentRecoveryService::recoverFromSuccessfulRetry()
                  → AuthorizeNetArbService::createRecoveredPaymentSubscription()
        ↓
FailedPaymentAuditLog row written for every branch
```

### Admin permission check (any `checkpermission:*` route)
```
Request → routes/admin.php (or failed_payments.php)
        ↓
App\Http\Middleware\CheckPermission::handle()
        ├─ Auth::guard('web')->user()->role_id === null  → allow (superadmin)
        ├─ else: json_decode(role->permissions) → in_array(required, permissions)?
        │       → yes: allow
        │       → no:  flash 'Permission denied...' → redirect admin.dashboard
        └─ (Failed Payment routes additionally re-check via FailedPaymentCasePolicy
            in the controller/authorize() call, same role_id-null convention)
```

---

## How to Use This Document

- Treat every claim above as **verified against source on 2026-08-20**
  unless explicitly marked "not verified" / "inferred" / "not directly read."
- Before modifying payment, webhook, or auth code, re-read the specific
  file(s) named in the relevant section — this document is a map, not a
  substitute for reading the code you're about to change.
- If you discover this document is out of date (a flag flipped, a class
  renamed, a table added), update the relevant section rather than leaving
  stale information for the next session.
