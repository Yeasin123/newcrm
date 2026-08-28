<?php

namespace App\Services\Messaging;

use App\Models\SalesmanMessage;
use App\Models\User;
use App\Services\Telephony\SmsGateway;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Admin↔salesman SMS threads. Ported from the legacy SalesmanMessageController
 * (index / store / webhook / orderedSalesmen / unreadCounts). Delivery goes
 * through the Phase 9f SmsGateway (Null unless telephony.sms_enabled).
 */
class SalesmanMessageService
{
    public function __construct(private readonly SmsGateway $sms) {}

    /** Salesmen (role_id set, active), most-recently-messaged first. */
    public function orderedSalesmen(int $adminId): Collection
    {
        $latestMessageAt = SalesmanMessage::query()
            ->select('created_at')
            ->whereColumn('salesman_messages.salesman_id', 'users.id')
            ->where('admin_id', $adminId)
            ->latest('created_at')
            ->limit(1);

        $unreadCount = SalesmanMessage::query()
            ->selectRaw('count(*)')
            ->whereColumn('salesman_messages.salesman_id', 'users.id')
            ->where('admin_id', $adminId)
            ->where('direction', 'inbound')
            ->whereNull('read_at');

        return User::query()
            ->whereNotNull('role_id')
            ->where('status', 1)
            ->select('users.*')
            ->selectSub($latestMessageAt, 'latest_salesman_message_at')
            ->selectSub($unreadCount, 'unread_salesman_message_count')
            ->orderByDesc('latest_salesman_message_at')
            ->orderByDesc('unread_salesman_message_count')
            ->orderBy('first_name')
            ->get();
    }

    /** @return array<int,int>  salesman_id => unread inbound count */
    public function unreadCounts(int $adminId): array
    {
        return SalesmanMessage::query()
            ->selectRaw('salesman_id, count(*) as total')
            ->where('admin_id', $adminId)
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->groupBy('salesman_id')
            ->pluck('total', 'salesman_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** @return Collection<int,SalesmanMessage> */
    public function thread(int $adminId, int $salesmanId): Collection
    {
        return SalesmanMessage::query()
            ->with(['admin', 'salesman'])
            ->where('admin_id', $adminId)
            ->where('salesman_id', $salesmanId)
            ->orderBy('created_at')
            ->get();
    }

    public function markThreadRead(int $adminId, int $salesmanId): void
    {
        SalesmanMessage::query()
            ->where('admin_id', $adminId)
            ->where('salesman_id', $salesmanId)
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function send(int $adminId, User $salesman, string $body): SalesmanMessage
    {
        $from = $this->sms->normalizePhone(config('services.telnyx.from_number'));
        $to = $this->sms->normalizePhone($salesman->contact);

        $message = SalesmanMessage::create([
            'admin_id' => $adminId,
            'salesman_id' => $salesman->id,
            'direction' => 'outbound',
            'status' => 'pending',
            'from_number' => $from,
            'to_number' => $to,
            'body' => strip_tags($body),
        ]);

        try {
            $result = $this->sms->send((string) $salesman->contact, $message->body, $from);

            $message->update([
                'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
                'telnyx_message_id' => $result['message_id'] ?? null,
                'telnyx_payload' => $result['payload'] ?? null,
                'error_message' => ($result['success'] ?? false) ? null : ($result['error'] ?? 'SMS send failed.'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Salesman SMS failed.', ['message_id' => $message->id, 'error' => $e->getMessage()]);
            $message->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }

        return $message->fresh();
    }

    /**
     * Record an inbound Telnyx SMS. Ported from SalesmanMessageController@webhook.
     *
     * @return array{status:string, code?:int}
     */
    public function recordInbound(array $payload, array $rawRequest): array
    {
        $body = data_get($payload, 'text') ?: data_get($payload, 'body.text');
        $fromNumber = data_get($payload, 'from.phone_number');
        $toNumber = data_get($payload, 'to.0.phone_number');

        if (empty($body) || empty($fromNumber)) {
            return ['status' => 'ignored'];
        }

        $salesman = User::query()
            ->whereNotNull('role_id')
            ->whereIn('contact', $this->sms->localPhoneVariants($fromNumber))
            ->first();

        if (! $salesman) {
            return ['status' => 'salesman_not_found'];
        }

        $adminId = SalesmanMessage::query()->where('salesman_id', $salesman->id)->latest()->value('admin_id')
            ?: User::query()->whereNull('role_id')->value('id');

        if (! $adminId) {
            return ['status' => 'admin_not_found', 'code' => 422];
        }

        SalesmanMessage::create([
            'admin_id' => $adminId,
            'salesman_id' => $salesman->id,
            'direction' => 'inbound',
            'status' => 'received',
            'from_number' => $this->sms->normalizePhone($fromNumber),
            'to_number' => $this->sms->normalizePhone($toNumber),
            'body' => $body,
            'telnyx_message_id' => data_get($payload, 'id'),
            'telnyx_payload' => $rawRequest,
        ]);

        return ['status' => 'received'];
    }
}
