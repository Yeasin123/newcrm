<?php

namespace App\Models;

use App\Enums\FailedPaymentCaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FailedPaymentCase extends Model
{
    use HasFactory;

    public const DEMO_FIXTURE_SET = 'failed-payment-local-demo-v1';

    public const DEMO_BATCH_ID = 'failed-payment-local-demo-batch-2026-08-04';

    public const DEMO_CASE_UUIDS = [
        'DEMO-FP-OPEN-001' => '11111111-1111-1111-1111-111111111111',
        'DEMO-FP-UNDERREVIEW-002' => '22222222-2222-2222-2222-222222222222',
        'DEMO-FP-RECOVERED-003' => '33333333-3333-3333-3333-333333333333',
        'DEMO-FP-UNMATCHED-004' => '44444444-4444-4444-4444-444444444444',
    ];

    protected $guarded = [];

    protected $casts = [
        'scheduled_payment_date' => 'date',
        'first_failed_at' => 'datetime',
        'latest_failed_at' => 'datetime',
        'next_action_at' => 'datetime',
        'recovered_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
        'failed_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'current_status' => FailedPaymentCaseStatus::NEW,
        'attempt_count' => 0,
        'priority' => 'normal',
    ];

    public function getRouteKeyName(): string
    {
        return 'case_uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            if (empty($case->case_uuid)) {
                $case->case_uuid = (string) Str::uuid();
            }
        });
    }

    public static function demoFixtureCaseKeys(): array
    {
        return array_keys(self::DEMO_CASE_UUIDS);
    }

    public static function demoFixtureCaseUuid(string $caseKey): ?string
    {
        return self::DEMO_CASE_UUIDS[$caseKey] ?? null;
    }

    public static function demoFixtureCaseMap(): array
    {
        return self::DEMO_CASE_UUIDS;
    }

    public static function demoFixtureSet(): string
    {
        return self::DEMO_FIXTURE_SET;
    }

    public static function demoFixtureBatchId(): string
    {
        return self::DEMO_BATCH_ID;
    }

    public function isExactDemoFixture(): bool
    {
        $metadata = (array) ($this->metadata ?? []);
        $caseKey = (string) ($this->case_key ?? '');
        $caseUuid = strtolower((string) ($this->case_uuid ?? ''));
        $expectedUuid = self::demoFixtureCaseUuid($caseKey);

        return (bool) data_get($metadata, 'demo_fixture')
            && (string) data_get($metadata, 'demo_fixture_set') === self::DEMO_FIXTURE_SET
            && (string) data_get($metadata, 'demo_batch_id') === self::DEMO_BATCH_ID
            && $expectedUuid !== null
            && strtolower($expectedUuid) === $caseUuid
            && (string) data_get($metadata, 'demo_case_key') === $caseKey
            && (string) data_get($metadata, 'demo_case_uuid') === $expectedUuid;
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withDefault();
    }

    public function paymentHistory()
    {
        return $this->belongsTo(PaymentHistory::class, 'payment_history_id');
    }

    public function authorizeAccount()
    {
        return $this->belongsTo(AuthorizeAccount::class, 'authorize_account_id');
    }

    public function webhookEvent()
    {
        return $this->belongsTo(AnetWebhookEvent::class, 'anet_webhook_event_id', 'event_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id')->withDefault();
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }

    public function attempts()
    {
        return $this->hasMany(FailedPaymentAttempt::class);
    }

    public function actions()
    {
        return $this->hasMany(FailedPaymentAction::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(FailedPaymentStatusHistory::class);
    }

    public function retrySchedules()
    {
        return $this->hasMany(FailedPaymentRetrySchedule::class);
    }

    public function webhookEvents()
    {
        return $this->hasMany(FailedPaymentWebhookEvent::class);
    }

    public function communications()
    {
        return $this->hasMany(FailedPaymentCommunication::class);
    }

    public function notes()
    {
        return $this->hasMany(FailedPaymentNote::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(FailedPaymentAuditLog::class);
    }
}
