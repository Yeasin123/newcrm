<?php

namespace App\Providers;

use App\Models\Claim;
use App\Models\Client;
use App\Models\FailedPaymentCase;
use App\Models\PaymentHistory;
use App\Models\User;
use App\Observers\ClaimObserver;
use App\Observers\ClientObserver;
use App\Observers\PaymentHistoryObserver;
use App\Policies\FailedPaymentCasePolicy;
use App\Services\Ai\ClaudeLlmGateway;
use App\Services\Ai\LlmGateway;
use App\Services\Ai\NullLlmGateway;
use App\Services\Billing\DryRunPaymentGateway;
use App\Services\Billing\LivePaymentGateway;
use App\Services\Billing\NullPaymentOutcomeNotifier;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\PaymentOutcomeNotifier;
use App\Services\Billing\ZapierPaymentOutcomeNotifier;
use App\Services\Dialer\HttpThreeCxGateway;
use App\Services\Dialer\NullThreeCxGateway;
use App\Services\Dialer\ThreeCxGateway;
use App\Services\FailedPayment\Contracts\FailedPaymentCommunicationGatewayInterface;
use App\Services\FailedPayment\Contracts\FailedPaymentRetryGatewayInterface;
use App\Services\FailedPayment\DryRunFailedPaymentCommunicationGateway;
use App\Services\FailedPayment\DryRunFailedPaymentRetryGateway;
use App\Services\FailedPayment\LiveFailedPaymentRetryGateway;
use App\Services\FailedPayment\UnavailableFailedPaymentCommunicationGateway;
use App\Services\FailedPayment\UnavailableFailedPaymentRetryGateway;
use App\Services\Lead\EntranceRoundRobinNotifier;
use App\Services\Lead\HttpEntranceRoundRobinNotifier;
use App\Services\Lead\NullEntranceRoundRobinNotifier;
use App\Services\Settings\AppSettingsService;
use App\Services\Telephony\JdiVoicemailGateway;
use App\Services\Telephony\NullSmsGateway;
use App\Services\Telephony\NullVoiceGateway;
use App\Services\Telephony\NullVoicemailGateway;
use App\Services\Telephony\SmsGateway;
use App\Services\Telephony\TelnyxSmsGateway;
use App\Services\Telephony\TwilioVoiceGateway;
use App\Services\Telephony\VoiceGateway;
use App\Services\Telephony\VoicemailGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PaymentOutcomeNotifier::class,
            fn () => config('billing.outbound_webhooks_enabled')
                ? new ZapierPaymentOutcomeNotifier
                : new NullPaymentOutcomeNotifier,
        );

        // Real money only moves when BOTH billing flags are 'live'.
        $this->app->bind(PaymentGateway::class, function ($app) {
            $live = config('billing.driver') === 'live' && config('billing.processing_mode') === 'live';

            return $live
                ? $app->make(LivePaymentGateway::class)
                : $app->make(DryRunPaymentGateway::class);
        });

        // Failed Payment retry gateway — two-flag gate. Live CIM charges only
        // when driver = live_cim AND processing_mode = live AND dry_run_mode
        // is off; when the flags say dry-run it simulates; otherwise it is
        // deliberately unavailable (blocks).
        $this->app->bind(FailedPaymentRetryGatewayInterface::class, function ($app) {
            $driver = strtolower((string) config('failed_payments.retry_gateway_driver', 'dry_run'));
            $mode = strtolower((string) config('failed_payments.retry_processing_mode', 'dry_run'));
            $dryRun = (bool) config('failed_payments.dry_run_mode', true);

            if ($driver === 'live_cim' && $mode === 'live' && ! $dryRun) {
                return $app->make(LiveFailedPaymentRetryGateway::class);
            }

            if ($dryRun && $driver === 'dry_run' && $mode === 'dry_run') {
                return $app->make(DryRunFailedPaymentRetryGateway::class);
            }

            return $app->make(UnavailableFailedPaymentRetryGateway::class);
        });

        $this->app->bind(FailedPaymentCommunicationGatewayInterface::class, function ($app) {
            return config('failed_payments.communication_simulation_enabled', true)
                ? $app->make(DryRunFailedPaymentCommunicationGateway::class)
                : $app->make(UnavailableFailedPaymentCommunicationGateway::class);
        });

        // Inbound entrance webhooks always process locally; the outbound
        // VanillaSoft round-robin only fires when explicitly enabled.
        $this->app->bind(
            EntranceRoundRobinNotifier::class,
            fn ($app) => config('entrances.outbound_enabled')
                ? $app->make(HttpEntranceRoundRobinNotifier::class)
                : $app->make(NullEntranceRoundRobinNotifier::class),
        );

        // Dialer caller-ID rotation records locally; the 3CX push is opt-in.
        $this->app->bind(
            ThreeCxGateway::class,
            fn ($app) => config('dialer.threecx_enabled')
                ? $app->make(HttpThreeCxGateway::class)
                : $app->make(NullThreeCxGateway::class),
        );

        // Telephony transports — Null gateway unless BOTH the comms master
        // switch AND the per-channel comms flag AND the transport's own
        // config/telephony.php flag are on. `comms.enabled` is authoritative:
        // when it is off nothing is contacted, whatever else is set.
        $this->app->bind(
            SmsGateway::class,
            fn ($app) => config('comms.enabled') && config('comms.sms') && config('telephony.sms_enabled')
                ? $app->make(TelnyxSmsGateway::class)
                : $app->make(NullSmsGateway::class),
        );
        $this->app->bind(
            VoiceGateway::class,
            fn ($app) => config('comms.enabled') && config('comms.voice') && config('telephony.voice_enabled')
                ? $app->make(TwilioVoiceGateway::class)
                : $app->make(NullVoiceGateway::class),
        );
        $this->app->bind(
            VoicemailGateway::class,
            fn ($app) => config('comms.enabled') && config('comms.voicemail') && config('telephony.voicemail_enabled')
                ? $app->make(JdiVoicemailGateway::class)
                : $app->make(NullVoicemailGateway::class),
        );

        // AI layer (Phase 11) — Null unless config('ai.enabled') + a key.
        $this->app->bind(LlmGateway::class, function ($app) {
            $claude = $app->make(ClaudeLlmGateway::class);

            return $claude->available()
                ? $claude
                : $app->make(NullLlmGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Control Centre — merge the DB setting overrides into live config
        // (billing / comms / integration flags) before any binding that
        // reads them resolves. Falls back silently to the .env defaults.
        app(AppSettingsService::class)->apply();

        // Hard email kill-switch. While comms are off, route mail to a
        // no-send mailer so nothing can reach a real customer — never in
        // the test suite, which manages its own mailer.
        if (! app()->runningUnitTests()
            && ! (config('comms.enabled') && config('comms.email'))) {
            config(['mail.default' => config('comms.suppressed_mailer', 'log')]);
        }

        // Superadmins (legacy `role_id IS NULL`) bypass every Gate check.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        Gate::policy(FailedPaymentCase::class, FailedPaymentCasePolicy::class);

        // Phase 10c — analytics mirror. The observers no-op unless
        // config('analytics.mirror_enabled'); LeadDetail already carries its own
        // #[ObservedBy(LeadDetailObserver::class)].
        Client::observe(ClientObserver::class);
        PaymentHistory::observe(PaymentHistoryObserver::class);
        Claim::observe(ClaimObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // NB: intentionally NOT Date::use(CarbonImmutable::class) — large amounts
        // of ported legacy code type-hint the mutable Carbon\Carbon directly.

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
