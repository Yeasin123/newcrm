<?php

namespace App\Services\Billing\Anet;

use App\Exceptions\AnetApiException;
use App\Models\AuthorizeAccount;

/**
 * Individual (non-recurring) transactions: one-time charge against a CIM
 * profile, refund, void, and transaction lookup. Ports the
 * `createTransactionRequest` / refund / void shapes from the legacy
 * SubscriptionController + PaymentRefundController.
 */
class TransactionGateway
{
    public function __construct(private readonly AnetClient $client) {}

    /**
     * authCaptureTransaction against a stored CIM customer + payment profile.
     *
     * @return array{transaction_id:string, auth_code:?string, raw:array}
     */
    public function chargeCustomerProfile(
        AuthorizeAccount $account,
        string $customerProfileId,
        string $paymentProfileId,
        float $amount,
        ?string $refId = null,
        ?string $invoiceNumber = null,
        ?string $description = null,
    ): array {
        $body = [
            'refId' => $refId ?: ('ref-'.random_int(100000000, 999999999)),
            'transactionRequest' => array_filter([
                'transactionType' => 'authCaptureTransaction',
                'amount' => round($amount, 2),
                'profile' => [
                    'customerProfileId' => $customerProfileId,
                    'paymentProfile' => ['paymentProfileId' => $paymentProfileId],
                ],
                'order' => array_filter([
                    'invoiceNumber' => $invoiceNumber,
                    'description' => $description,
                ]),
            ]),
        ];

        return $this->parseTransactionResponse($this->client->call($account, 'createTransactionRequest', $body));
    }

    /** authCapture against a raw card (legacy fallback path). */
    public function chargeCard(
        AuthorizeAccount $account,
        string $cardNumber,
        string $expMonth,
        string $expYear,
        float $amount,
        array $billTo = [],
        ?string $refId = null,
    ): array {
        $body = [
            'refId' => $refId ?: ('trans-'.time()),
            'transactionRequest' => array_filter([
                'transactionType' => 'authCaptureTransaction',
                'amount' => round($amount, 2),
                'payment' => [
                    'creditCard' => [
                        'cardNumber' => preg_replace('/\D+/', '', $cardNumber),
                        'expirationDate' => $expYear.'-'.str_pad($expMonth, 2, '0', STR_PAD_LEFT),
                    ],
                ],
                'billTo' => array_filter($billTo),
            ]),
        ];

        return $this->parseTransactionResponse($this->client->call($account, 'createTransactionRequest', $body));
    }

    /**
     * authCapture against a bank account (eCheck).
     *
     * @param  array<string,string>  $bank  routingNumber,accountNumber,bankAccountName,bankAccountType,echeckType,bankName
     */
    public function chargeBank(
        AuthorizeAccount $account,
        array $bank,
        float $amount,
        array $billTo = [],
        ?string $refId = null,
    ): array {
        $body = [
            'refId' => $refId ?: ('trans-'.time()),
            'transactionRequest' => array_filter([
                'transactionType' => 'authCaptureTransaction',
                'amount' => round($amount, 2),
                'payment' => ['bankAccount' => self::bankBlock($bank)],
                'billTo' => array_filter($billTo),
            ]),
        ];

        return $this->parseTransactionResponse($this->client->call($account, 'createTransactionRequest', $body));
    }

    /** @param array<string,string> $bank */
    public static function bankBlock(array $bank): array
    {
        return array_filter([
            'accountType' => strtolower((string) ($bank['bankAccountType'] ?? 'checking')),
            'routingNumber' => $bank['routingNumber'] ?? null,
            'accountNumber' => $bank['accountNumber'] ?? null,
            'nameOnAccount' => $bank['bankAccountName'] ?? null,
            'echeckType' => $bank['echeckType'] ?? null,
            'bankName' => $bank['bankName'] ?? null,
        ]);
    }

    /**
     * Refund a settled transaction. Authorize.Net accepts the card last 4
     * with a literal 'XXXX' expiration for CIM-originated refunds.
     */
    public function refund(AuthorizeAccount $account, string $refTransId, float $amount, string $cardLast4): array
    {
        $body = [
            'transactionRequest' => [
                'transactionType' => 'refundTransaction',
                'amount' => round($amount, 2),
                'payment' => [
                    'creditCard' => ['cardNumber' => $cardLast4, 'expirationDate' => 'XXXX'],
                ],
                'refTransId' => $refTransId,
            ],
        ];

        return $this->parseTransactionResponse($this->client->call($account, 'createTransactionRequest', $body));
    }

    /** Void an unsettled transaction. */
    public function void(AuthorizeAccount $account, string $refTransId): array
    {
        $body = [
            'transactionRequest' => [
                'transactionType' => 'voidTransaction',
                'refTransId' => $refTransId,
            ],
        ];

        return $this->parseTransactionResponse($this->client->call($account, 'createTransactionRequest', $body));
    }

    /** @return array<string,mixed>|null */
    public function getTransactionDetails(AuthorizeAccount $account, string $transactionId): ?array
    {
        try {
            return $this->client->call($account, 'getTransactionDetailsRequest', [
                'transId' => $transactionId,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array{transaction_id:string, auth_code:?string, raw:array} */
    private function parseTransactionResponse(array $response): array
    {
        $txn = data_get($response, 'transactionResponse', []);
        $responseCode = (string) data_get($txn, 'responseCode', '');

        // responseCode 1 = approved, 4 = held for review; anything else is a decline.
        if (! in_array($responseCode, ['1', '4'], true)) {
            $err = data_get($txn, 'errors.0', []);
            throw new AnetApiException(
                (string) data_get($err, 'errorCode', 'E_DECLINE'),
                (string) data_get($err, 'errorText', 'Transaction was not approved.'),
                $response,
            );
        }

        return [
            'transaction_id' => (string) data_get($txn, 'transId', ''),
            'auth_code' => data_get($txn, 'authCode'),
            'raw' => $response,
        ];
    }
}
