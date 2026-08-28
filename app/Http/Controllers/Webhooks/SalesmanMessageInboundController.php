<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\Messaging\SalesmanMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /telnyx/salesman-messages/webhook — inbound SMS from a salesman.
 * Ported from SalesmanMessageController@webhook; response bodies identical.
 */
class SalesmanMessageInboundController
{
    public function __invoke(Request $request, SalesmanMessageService $service): JsonResponse
    {
        $payload = $request->input('data.payload', $request->input('payload', $request->all()));

        $result = $service->recordInbound((array) $payload, $request->all());

        return response()->json(['status' => $result['status']], $result['code'] ?? 200);
    }
}
