<?php

namespace App\Services\Billing;

/**
 * Normalised payment instrument for a checkout. Exactly one of:
 *   - opaque Accept.js token (preferred: raw card never touches the server)
 *   - raw card number + expiry (legacy fallback)
 *   - bank account fields (eCheck)
 *
 * `paymentType` mirrors the legacy `clients.payment_type` / request field:
 * '1' = bank/ACH, anything else = card.
 */
class PaymentInput
{
    /**
     * @param  array<string,string>  $bank  routingNumber, accountNumber, bankAccountName, bankAccountType, echeckType, bankName
     */
    public function __construct(
        public readonly string $paymentType = '0',
        public readonly ?string $cardNumber = null,
        public readonly ?string $expMonth = null,
        public readonly ?string $expYear = null,
        public readonly ?string $opaqueDataDescriptor = null,
        public readonly ?string $opaqueDataValue = null,
        public readonly array $bank = [],
        // Non-sensitive card metadata that rides alongside an opaque token
        // (Accept.js returns these to the browser; the raw PAN never does).
        public readonly ?string $explicitLast4 = null,
        public readonly ?string $brand = null,
    ) {}

    public function isBank(): bool
    {
        return $this->paymentType === '1';
    }

    public function hasOpaqueToken(): bool
    {
        return filled($this->opaqueDataDescriptor) && filled($this->opaqueDataValue);
    }

    public function hasRawCard(): bool
    {
        return filled($this->cardNumber) && filled($this->expMonth) && filled($this->expYear);
    }

    public function last4(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->cardNumber);

        if (strlen((string) $digits) >= 4) {
            return substr($digits, -4);
        }

        $explicit = preg_replace('/\D+/', '', (string) $this->explicitLast4);

        return strlen((string) $explicit) === 4 ? $explicit : null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            paymentType: (string) ($data['payment_type'] ?? '0'),
            cardNumber: $data['card_number'] ?? null,
            expMonth: isset($data['expMonth']) ? (string) $data['expMonth'] : null,
            expYear: isset($data['expYear']) ? (string) $data['expYear'] : null,
            opaqueDataDescriptor: $data['opaqueDataDescriptor'] ?? $data['dataDescriptor'] ?? null,
            opaqueDataValue: $data['opaqueDataValue'] ?? $data['dataValue'] ?? null,
            explicitLast4: isset($data['last4']) ? (string) $data['last4'] : null,
            brand: $data['brand'] ?? null,
            bank: array_filter([
                'routingNumber' => $data['routingNumber'] ?? null,
                'accountNumber' => $data['accountNumber'] ?? null,
                'bankAccountName' => $data['bankAccountName'] ?? null,
                'bankAccountType' => $data['bankAccountType'] ?? null,
                'echeckType' => $data['echeckType'] ?? null,
                'bankName' => $data['bankName'] ?? null,
            ]),
        );
    }
}
