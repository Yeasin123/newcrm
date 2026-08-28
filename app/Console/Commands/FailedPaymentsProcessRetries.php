<?php

namespace App\Console\Commands;

use App\Enums\FailedPaymentRetryExecutionIntent;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\FailedPaymentRetrySchedule;
use App\Services\FailedPayment\FailedPaymentRetryProcessorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Ported near-verbatim from the legacy `failed-payments:process-retries`
 * command. Walks every due PENDING retry schedule and hands it to
 * FailedPaymentRetryProcessorService.
 *
 * Guard rails match the legacy exactly:
 *   --simulate  requires failed_payments.dry_run_mode = true;
 *   live run    requires retry_processing_mode = live AND
 *               retry_gateway_driver = live_cim.
 * A cache lock keeps two runs from overlapping.
 */
class FailedPaymentsProcessRetries extends Command
{
    protected $signature = 'failed-payments:process-retries {--simulate : Run in dry-run simulation mode.} {--limit=25 : Maximum retry schedules to process in one run.}';

    protected $description = 'Process failed-payment retry schedules in simulation or live automatic mode.';

    public function handle(FailedPaymentRetryProcessorService $processor): int
    {
        $simulate = (bool) $this->option('simulate');
        $processingMode = strtolower((string) config('failed_payments.retry_processing_mode', 'dry_run'));
        $intent = $simulate ? FailedPaymentRetryExecutionIntent::SIMULATION : FailedPaymentRetryExecutionIntent::AUTOMATIC;

        if ($simulate && ! config('failed_payments.dry_run_mode')) {
            $this->error('Dry-run mode must be enabled before retry simulation can run.');

            return self::FAILURE;
        }

        if (! $simulate && $processingMode !== 'live') {
            $this->error('Live retry processing is disabled. Set FAILED_PAYMENT_RETRY_PROCESSING_MODE=live to enable the cron run.');

            return self::FAILURE;
        }

        if (! $simulate && strtolower((string) config('failed_payments.retry_gateway_driver', 'dry_run')) !== 'live_cim') {
            $this->error('Live retry gateway is disabled. Set FAILED_PAYMENT_RETRY_GATEWAY_DRIVER=live_cim to enable the cron run.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $lockKey = 'failed-payments:process-retries:lock';
        $lockAcquired = false;

        try {
            if (! Cache::add($lockKey, true, 300)) {
                $this->warn('Skipped: another failed-payment retry run is already in progress.');

                return self::SUCCESS;
            }

            $lockAcquired = true;

            $timezone = (string) config('failed_payments.retry_timezone', config('app.timezone', 'UTC'));
            $now = Carbon::now($timezone);
            $schedules = FailedPaymentRetrySchedule::query()
                ->with('failedPaymentCase')
                ->where('status', FailedPaymentRetryScheduleStatus::PENDING)
                ->where(function ($query) use ($now): void {
                    $query->whereNull('scheduled_at')
                        ->orWhere('scheduled_at', '<=', $now);
                })
                ->orderBy('scheduled_at')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $selected = $schedules->count();
            $processed = 0;
            $completed = 0;
            $deferred = 0;
            $blocked = 0;
            $skipped = 0;
            $duplicates = 0;
            $failed = 0;

            foreach ($schedules as $schedule) {
                try {
                    $result = $processor->processSchedule($schedule, [
                        'execution_intent' => $intent,
                    ]);

                    $processed++;

                    match ($result['status'] ?? null) {
                        'completed', 'simulated' => $completed++,
                        'deferred' => $deferred++,
                        'duplicate_simulation' => $duplicates++,
                        'skipped' => $skipped++,
                        default => $blocked++,
                    };
                } catch (\Throwable $e) {
                    $failed++;
                    report($e);
                    $this->warn('Retry schedule '.$schedule->id.' failed unexpectedly and was left pending.');
                }
            }

            $runLabel = $simulate ? 'dry-run retry simulation' : 'live automatic retry processing';
            $this->info(sprintf(
                'Completed %s. Selected: %d, Processed: %d, Completed: %d, Deferred: %d, Duplicates: %d, Skipped: %d, Blocked: %d, Failed: %d',
                $runLabel,
                $selected,
                $processed,
                $completed,
                $deferred,
                $duplicates,
                $skipped,
                $blocked,
                $failed
            ));

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error($simulate ? 'Retry simulation failed unexpectedly.' : 'Live retry processing failed unexpectedly.');

            return self::FAILURE;
        } finally {
            if ($lockAcquired) {
                Cache::forget($lockKey);
            }
        }
    }
}
