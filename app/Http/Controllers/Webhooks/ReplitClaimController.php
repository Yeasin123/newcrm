<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\Claim;
use App\Models\ClaimWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound claim-status webhook from the external Replit automation.
 *
 * Response contract is kept byte-identical to the legacy
 * `Replit\ReplitWebhookController@claimStatus`:
 *   200 {success:true,  message, claim_no}
 *   422 {success:false, message}                — claim number missing
 *   404 {success:false, message, claim_no}      — claim not found
 *   500 {success:false, message, error}         — unexpected failure
 *
 * Improvement over the legacy handler: every call is recorded in
 * `claim_webhook_logs` (matched or not).
 */
class ReplitClaimController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        try {
            $claimNo = data_get($payload, 'claim.claim_number')
                ?? data_get($payload, 'claim.claim_id')
                ?? data_get($payload, 'claim_no')
                ?? data_get($payload, 'claim_number');

            $event = data_get($payload, 'event');
            $workflowStep = data_get($payload, 'status.workflow_step');
            $status = data_get($payload, 'status.status');
            $subStatus = data_get($payload, 'status.sub_status');

            if (blank($claimNo)) {
                $this->logEvent(null, null, $payload, compact('event', 'workflowStep', 'status', 'subStatus'), false, 'claim number missing');

                Log::warning('Replit webhook claim number missing', ['payload' => $payload]);

                return response()->json([
                    'success' => false,
                    'message' => 'Claim number not found in payload.',
                ], 422);
            }

            $claim = Claim::where('claim_no', (string) $claimNo)->first();

            if (! $claim) {
                $this->logEvent(null, (string) $claimNo, $payload, compact('event', 'workflowStep', 'status', 'subStatus'), false, 'claim not found');

                Log::warning('Claim not found for webhook payload', ['claim_no' => $claimNo, 'payload' => $payload]);

                return response()->json([
                    'success' => false,
                    'message' => 'Claim not found.',
                    'claim_no' => (string) $claimNo,
                ], 404);
            }

            $claim->forceFill([
                'replit_claim_payload' => $payload,
                'replit_event' => $event,
                'replit_workflow_step' => $workflowStep,
                'replit_status' => $status,
                'replit_sub_status' => $subStatus,
                'replit_payload_received_at' => now(),
            ])->save();

            $this->logEvent($claim->id, $claim->claim_no, $payload, compact('event', 'workflowStep', 'status', 'subStatus'), true, null);

            return response()->json([
                'success' => true,
                'message' => 'Claim webhook data saved successfully.',
                'claim_no' => $claim->claim_no,
            ]);
        } catch (\Throwable $e) {
            Log::error('Replit webhook save failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'payload' => $payload,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while saving webhook data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function logEvent(?int $claimId, ?string $claimNo, array $payload, array $parts, bool $matched, ?string $note): void
    {
        try {
            ClaimWebhookLog::create([
                'claim_id' => $claimId,
                'claim_no' => $claimNo,
                'source' => 'replit',
                'event' => $parts['event'],
                'workflow_step' => $parts['workflowStep'],
                'status' => $parts['status'],
                'sub_status' => $parts['subStatus'],
                'payload' => $payload,
                'received_at' => now(),
                'matched_claim' => $matched,
                'note' => $note,
            ]);
        } catch (\Throwable $e) {
            Log::warning('claim_webhook_logs write failed', ['error' => $e->getMessage()]);
        }
    }
}
