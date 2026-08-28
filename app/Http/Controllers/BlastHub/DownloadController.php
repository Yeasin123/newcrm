<?php

namespace App\Http\Controllers\BlastHub;

use App\Models\LeadBlastRun;
use App\Services\Lead\LeadBlasterService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET blast-hub/download/{run} — build the run's CSV (once), lock every
 * selected lead out of future runs for 28 days, and stream the file. Ported
 * from LeadBlasterController@download.
 */
class DownloadController
{
    public function __invoke(LeadBlastRun $run, LeadBlasterService $service): StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('Lead Blaster'), 403);

        $path = $service->createCsv($run);
        $service->lockRunItems($run);

        $fileName = 'lead-blaster-run-'.$run->id.'-'.now()->format('Ymd_His').'.csv';

        return Storage::disk('local')->download($path, $fileName, ['Content-Type' => 'text/csv']);
    }
}
