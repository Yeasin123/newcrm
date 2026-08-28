<?php

namespace App\Http\Controllers\Customers;

use App\Enums\PaymentStructure;
use App\Models\Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the current Customers filter/segment as a CSV. A plain GET route so
 * the browser downloads it natively (no Livewire payload round-trip).
 */
class ExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $segment = (string) $request->query('segment', 'all');
        $segment = array_key_exists($segment, Client::SEGMENTS) ? $segment : 'all';

        $query = Client::query()
            ->segment($segment)
            ->search($request->query('search'));

        if ($salesman = $request->query('salesman')) {
            $query->where('salesman_name', $salesman);
        }

        $columns = [
            'id', 'policy_number', 'first_name', 'last_name', 'email', 'contact',
            'city', 'state', 'zip_code', 'payment_structure', 'salesman_name',
            'status', 'login_off', 'star', 'start_date', 'end_date', 'created_at',
        ];

        $filename = 'customers-'.$segment.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            $query->orderBy('id')->toBase()->chunk(1000, function ($rows) use ($out, $columns) {
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($columns as $col) {
                        $line[] = $col === 'payment_structure'
                            ? PaymentStructure::tryLabel($row->payment_structure ?? null)
                            : ($row->{$col} ?? '');
                    }
                    fputcsv($out, $line);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
