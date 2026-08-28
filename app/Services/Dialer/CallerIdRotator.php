<?php

namespace App\Services\Dialer;

use App\Models\BuyNumber;
use App\Models\LeadDetail;
use App\Models\User;

/**
 * Picks the outbound caller ID for a dialer call and pushes it to 3CX. Ported
 * from the legacy CallerForVanilasoftController::checkNumber:
 *
 *   - prefer a number sharing the lead's area code (first 3 digits), taking
 *     the first un-locked one and locking it; if all are locked, release the
 *     whole area-code batch and re-take one
 *   - otherwise fall back to a number in the request state, then the lead's
 *     state (abbreviation expanded), then the configured fallback state, then
 *     anything in the pool — chosen at random
 *   - base64 the chosen number and hand (ext_number, callerId) to the 3CX
 *     gateway
 */
class CallerIdRotator
{
    public function __construct(private readonly ThreeCxGateway $threeCx) {}

    /**
     * @param  array<string,mixed>  $data  {contact, salesman, state?}
     * @return array{caller_id:?string, number:?string, gateway:mixed}
     */
    public function rotate(array $data): array
    {
        $extNumber = User::query()->where('v_salesman_id', $data['salesman'] ?? null)->first()?->ext_number;

        $contact = str_replace(['(', ')'], '', (string) ($data['contact'] ?? ''));
        $lead = LeadDetail::query()->where('contact', $contact)->first();
        $areaCode = substr($contact, 0, 3);

        $areaMatches = BuyNumber::query()->where('contact_no', 'like', $areaCode.'%')->get();

        $chosen = $areaMatches->isEmpty()
            ? $this->pickByState($data, $lead)
            : $this->pickByAreaCode($areaCode);

        if (! $chosen) {
            return ['caller_id' => null, 'number' => null, 'gateway' => ['error' => 'no_number_available']];
        }

        $callerId = base64_encode((string) $chosen->contact_no);

        return [
            'caller_id' => $callerId,
            'number' => $chosen->contact_no,
            'gateway' => $this->threeCx->updateOutboundCallerId($extNumber, $callerId),
        ];
    }

    protected function pickByAreaCode(string $areaCode): ?BuyNumber
    {
        $free = BuyNumber::query()->where('contact_no', 'like', $areaCode.'%')->whereNull('lock_number')->first();

        if ($free) {
            $free->update(['lock_number' => 'Lock']);

            return $free;
        }

        // All locked — release the batch and re-take.
        BuyNumber::query()->where('contact_no', 'like', $areaCode.'%')->whereNotNull('lock_number')
            ->update(['lock_number' => null]);

        $free = BuyNumber::query()->where('contact_no', 'like', $areaCode.'%')->whereNull('lock_number')->first();

        if (! $free) {
            return BuyNumber::query()->inRandomOrder()->first();
        }

        $free->update(['lock_number' => 'Lock']);

        return $free;
    }

    protected function pickByState(array $data, ?LeadDetail $lead): ?BuyNumber
    {
        if (! empty($data['state'])) {
            $numbers = BuyNumber::query()->where('state', $data['state'])->get();

            if ($numbers->isEmpty() && ! empty($lead?->state)) {
                $numbers = BuyNumber::query()->where('state', $this->stateFullName((string) $lead->state))->get();
            }
        } else {
            $numbers = BuyNumber::query()->where('state', config('dialer.fallback_state', 'NV'))->get();
        }

        if ($numbers->isEmpty()) {
            $numbers = BuyNumber::query()->get();
        }

        return $numbers->isEmpty() ? null : $numbers->random();
    }

    protected function stateFullName(string $abbreviation): string
    {
        static $map = [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
            'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
            'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
            'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
            'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
            'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
            'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        ];

        return $map[$abbreviation] ?? 'Unknown State';
    }
}
