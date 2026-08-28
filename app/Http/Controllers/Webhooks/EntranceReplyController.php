<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\Lead\EntranceReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single entry point for every inbound entrance SMS-reply webhook. The channel
 * slug comes from the route default; behaviour + response shape come from
 * config('entrances.channels.<slug>'). Paths and JSON bodies are byte-identical
 * to the legacy per-endpoint controllers.
 */
class EntranceReplyController
{
    public function __construct(private readonly EntranceReplyService $service) {}

    public function __invoke(Request $request, string $channel): JsonResponse
    {
        $config = config("entrances.channels.$channel");

        abort_if(! is_array($config), 404);

        $style = $config['response'] ?? 'plain';

        return match ($style) {
            'trace' => $this->traced($request, $config),
            'yair' => $this->yair($request, $config),
            default => $this->plain($request, $config),
        };
    }

    /** Numbered entrances: swallow everything, always 200 {status:success}. */
    protected function plain(Request $request, array $config): JsonResponse
    {
        try {
            $this->service->handle($request->all(), $config);
        } catch (\Throwable $e) {
            Log::error('Entrance webhook error.', ['channel' => $config['webhook_name'], 'error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success']);
    }

    /** MM / AVCHAD: trace id, 500 on a thrown error, otherwise 200. */
    protected function traced(Request $request, array $config): JsonResponse
    {
        $traceId = (string) Str::uuid();

        try {
            $ok = $this->service->handle($request->all(), $config);

            if (! $ok) {
                Log::warning('Entrance webhook missing number.', ['channel' => $config['webhook_name'], 'trace_id' => $traceId]);
            }
        } catch (\Throwable $e) {
            Log::error('Entrance webhook error.', [
                'channel' => $config['webhook_name'], 'trace_id' => $traceId, 'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'trace_id' => $traceId], 500);
        }

        return response()->json(['status' => 'success', 'trace_id' => $traceId]);
    }

    /** YAIR: multi-format payload extraction, 400 on empty, 500 on error. */
    protected function yair(Request $request, array $config): JsonResponse
    {
        $traceId = (string) Str::uuid();
        $headers = ['Content-Type' => 'application/json'];

        try {
            $payload = $this->extractPayload($request);

            if ($payload === []) {
                Log::warning('YAIR payload empty or invalid.', [
                    'trace_id' => $traceId, 'content_type' => $request->header('Content-Type'),
                ]);

                return response()->json([
                    'status' => 'error', 'message' => 'Invalid or empty payload', 'trace_id' => $traceId,
                ], 400, $headers);
            }

            $this->service->handle($payload, $config);

            return response()->json(['status' => 'success', 'trace_id' => $traceId], 200, $headers);
        } catch (\Throwable $e) {
            Log::error('YAIR Webhook ERROR', [
                'trace_id' => $traceId, 'message' => $e->getMessage(),
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error', 'message' => $e->getMessage(), 'trace_id' => $traceId,
            ], 500, $headers);
        }
    }

    /** @return array<string,mixed> */
    protected function extractPayload(Request $request): array
    {
        $data = $request->all();
        if (! empty($data)) {
            return $data;
        }

        $json = $request->json()->all();
        if (! empty($json)) {
            return $json;
        }

        $raw = $request->getContent();
        if (! empty($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            $contentType = strtolower((string) $request->header('Content-Type', ''));
            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                parse_str($raw, $parsed);
                if (! empty($parsed) && is_array($parsed)) {
                    return $parsed;
                }
            }
        }

        return [];
    }
}
