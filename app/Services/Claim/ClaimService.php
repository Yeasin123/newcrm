<?php

namespace App\Services\Claim;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\ClaimClosedDetail;
use App\Models\ClaimTrack;
use Illuminate\Support\Facades\DB;

/**
 * Claim lifecycle operations, extracted from the legacy fat controllers.
 * Every state change also drops a `claim_tracks` row so the timeline stays
 * complete.
 */
class ClaimService
{
    public function assignTechnician(Claim $claim, ?int $technicianId, ?string $schedule = null): void
    {
        DB::transaction(function () use ($claim, $technicianId, $schedule) {
            $claim->forceFill([
                'tech_id' => $technicianId,
                'tech_schedule' => $schedule ?: $claim->tech_schedule,
            ])->save();

            $this->track($claim, $technicianId ? 'Technician assigned' : 'Technician unassigned');
        });
    }

    public function setCallFee(Claim $claim, bool $isCallFee, ?float $owed = null): void
    {
        $claim->forceFill([
            'is_callfee' => $isCallFee,
            'owed_price' => $owed ?? $claim->owed_price,
        ])->save();

        $this->track($claim, $isCallFee ? 'Marked as call fee' : 'Call fee cleared');
    }

    public function changeStatus(Claim $claim, ClaimStatus $status): void
    {
        $claim->forceFill(['status' => $status->value])->save();

        $this->track($claim, 'Status → '.$status->label(), $status->value);
    }

    /**
     * Close a claim: record the closing summary and move it to Closed.
     */
    public function close(Claim $claim, array $data): ClaimClosedDetail
    {
        return DB::transaction(function () use ($claim, $data) {
            $detail = ClaimClosedDetail::create([
                'claim_id' => $claim->id,
                'technician_id' => $data['technician_id'] ?? $claim->tech_id ?? 0,
                'dispatch_amount' => $data['dispatch_amount'] ?? 0,
                'part_name' => $data['part_name'] ?? null,
                'part_cost' => $data['part_cost'] ?? 0,
                'currency' => $data['currency'] ?? '$',
                'notes' => $data['notes'] ?? null,
                'claim_spent_price' => $data['claim_spent_price'] ?? null,
            ]);

            $claim->forceFill([
                'status' => ClaimStatus::Closed->value,
                'total_price' => (float) ($data['claim_spent_price'] ?? $claim->total_price),
            ])->save();

            $this->track($claim, 'Claim closed', ClaimStatus::Closed->value);

            return $detail;
        });
    }

    public function addNote(Claim $claim, string $comment): ClaimTrack
    {
        return $this->track($claim, $comment);
    }

    private function track(Claim $claim, string $text, ?int $status = null): ClaimTrack
    {
        return ClaimTrack::create([
            'claim_id' => $claim->id,
            'user_id' => auth()->id(),
            'create_date' => now()->toDateTimeString(),
            'status' => $status,
            'button_text' => $text,
            'comments' => $text,
            'who_view' => auth()->user()?->name,
        ]);
    }
}
