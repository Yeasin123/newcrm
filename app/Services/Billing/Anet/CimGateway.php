<?php

namespace App\Services\Billing\Anet;

use App\Exceptions\AnetApiException;
use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Support\CardBrand;

/**
 * Customer Information Manager operations. Ports `AnetCimService` to the JSON
 * API — same two create paths (raw card, Accept.js opaque token), same
 * billTo-inside-payment-profile layout that avoids the E00014 error.
 */
class CimGateway
{
    public function __construct(private readonly AnetClient $client) {}

    /** Pure card-brand detection. */
    public function detectBrand(?string $cardNumber): ?string
    {
        return CardBrand::detect($cardNumber);
    }

    /**
     * Create a CIM customer + payment profile from a raw card.
     *
     * @return array{customer_profile_id:string, payment_profile_id:string}
     */
    public function createProfileFromCard(
        AuthorizeAccount $account,
        Client $client,
        string $cardNumber,
        string $expMonth,
        string $expYear,
    ): array {
        $billTo = array_filter([
            'firstName' => (string) ($client->first_name ?? ''),
            'lastName' => (string) ($client->last_name ?? ''),
            'address' => $client->address ?: null,
            'city' => $client->city ?: null,
            'state' => $client->state ?: null,
            'zip' => $client->zip_code ?: null,
            'phoneNumber' => $client->contact ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $body = [
            'refId' => 'cim-'.time().'-'.$client->id,
            'profile' => [
                'merchantCustomerId' => (string) $client->id,
                'email' => $client->email ?: null,
                'paymentProfiles' => [
                    'customerType' => 'individual',
                    'billTo' => $billTo,
                    'payment' => [
                        'creditCard' => [
                            'cardNumber' => preg_replace('/\D+/', '', $cardNumber),
                            'expirationDate' => sprintf('%04d-%02d', (int) $expYear, (int) $expMonth),
                        ],
                    ],
                ],
            ],
        ];

        return $this->parseProfileResponse(
            $this->client->call($account, 'createCustomerProfileRequest', $this->stripNulls($body)),
        );
    }

    /**
     * Create a CIM customer + payment profile from an Accept.js opaque token
     * (raw card never touches our server).
     *
     * @param  array<string,string>  $billTo  first_name,last_name,email,address,city,zip
     * @return array{customer_profile_id:string, payment_profile_id:string}
     */
    public function createProfileFromOpaque(
        AuthorizeAccount $account,
        array $billTo,
        string $dataDescriptor,
        string $dataValue,
    ): array {
        $billToBlock = array_filter([
            'firstName' => (string) ($billTo['first_name'] ?? 'Unknown'),
            'lastName' => (string) ($billTo['last_name'] ?? 'Unknown'),
            'address' => $billTo['address'] ?? null,
            'city' => $billTo['city'] ?? null,
            'zip' => $billTo['zip'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $body = [
            'refId' => 'cim-opaque-'.time(),
            'profile' => [
                'merchantCustomerId' => 'PC-'.time(),
                'email' => $billTo['email'] ?? null,
                'paymentProfiles' => [
                    'customerType' => 'individual',
                    'billTo' => $billToBlock,
                    'payment' => [
                        'opaqueData' => [
                            'dataDescriptor' => $dataDescriptor,
                            'dataValue' => $dataValue,
                        ],
                    ],
                    'defaultPaymentProfile' => true,
                ],
            ],
        ];

        return $this->parseProfileResponse(
            $this->client->call($account, 'createCustomerProfileRequest', $this->stripNulls($body)),
        );
    }

    /** @return array{customer_profile_id:string, payment_profile_id:string} */
    private function parseProfileResponse(array $response): array
    {
        $customerProfileId = (string) data_get($response, 'customerProfileId', '');
        $paymentProfileId = (string) data_get($response, 'customerPaymentProfileIdList.0', '')
            ?: (string) data_get($response, 'customerPaymentProfileIdList.numericString.0', '');

        if ($customerProfileId === '' || $paymentProfileId === '') {
            throw new AnetApiException('E_CIM_IDS', 'CIM profile created but IDs missing from response.', $response);
        }

        return [
            'customer_profile_id' => $customerProfileId,
            'payment_profile_id' => $paymentProfileId,
        ];
    }

    /** @param array<string,mixed> $data */
    private function stripNulls(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->stripNulls($value);
            } elseif ($value === null) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
