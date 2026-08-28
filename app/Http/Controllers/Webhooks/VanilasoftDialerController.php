<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\VanilasoftDialCall;
use App\Models\User;
use App\Services\Dialer\CallerIdRotator;
use App\Services\Dialer\ViewboardDispositionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vanilasoft dialer hooks. Ported from the legacy VanilasoftController — paths
 * and response bodies are byte-identical.
 */
class VanilasoftDialerController
{
    /** POST /vanilasoft/user/info — rotate the outbound caller ID for a call. */
    public function userInfo(Request $request, CallerIdRotator $rotator): string
    {
        $data = (array) $request->input('contact', []);

        $rotator->rotate($data);

        VanilasoftDialCall::dispatch([
            'contact' => $data['contact'] ?? null,
            'userid' => $data['salesman'] ?? null,
        ]);

        return 'ok';
    }

    /** POST /check/salesman/id — confirm the caller's Vanilasoft id matches their user. */
    public function checkSalesmanId(Request $request): Response
    {
        abort_unless(auth()->check(), 401);

        $vId = User::query()
            ->where('v_salesman_id', $request->input('salesman_id'))
            ->where('id', auth()->id())
            ->value('v_salesman_id');

        // Legacy emits a bare JSON `null` when there is no match (this Laravel's
        // response()->json(null) would emit `{}`, which the dialer reads as a hit).
        return response((string) json_encode($vId), 200, ['Content-Type' => 'application/json']);
    }

    /** POST /vanilasoft/viewboard/info — record a call disposition. */
    public function viewboardInfo(Request $request, ViewboardDispositionService $service): Response|string
    {
        $data = (array) $request->input('contact', []);

        return $service->record($data)
            ? response('ok')
            : response('', 200);
    }
}
