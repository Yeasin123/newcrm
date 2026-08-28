<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\File;

/**
 * Best-effort scan of storage/logs/laravel.log for Authorize.Net error / retry
 * lines. Ported from GatewayMonitorController::readLogEntries.
 */
class LogScanner
{
    /** @return array{errors:array<int,array>, retries:array<int,array>} */
    public function anetEntries(int $limit = 25): array
    {
        $errors = [];
        $retries = [];
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path) || ! File::isFile($path)) {
            return ['errors' => [], 'retries' => []];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            if (! is_string($line) || $line === '') {
                continue;
            }

            $entry = $this->parseLine($line);
            if (! $entry) {
                continue;
            }

            $message = strtolower($entry['message']);
            if (! str_contains($message, 'anet') && ! str_contains($message, 'authorize.net')) {
                continue;
            }

            if (str_contains($message, 'retry')) {
                $retries[] = $entry;
            }

            if (in_array($entry['level'], ['ERROR', 'WARNING'], true)
                || str_contains($message, 'failed') || str_contains($message, 'exception')) {
                $errors[] = $entry;
            }
        }

        return [
            'errors' => array_slice(array_reverse($errors), 0, $limit),
            'retries' => array_slice(array_reverse($retries), 0, $limit),
        ];
    }

    protected function parseLine(string $line): ?array
    {
        if (! preg_match('/^\[(?<time>[^\]]+)\]\s+\w+\.(?<level>[A-Z]+):\s+(?<message>.*)$/', $line, $m)) {
            return null;
        }

        return [
            'time' => $m['time'] ?? null,
            'level' => $m['level'] ?? 'INFO',
            'message' => $m['message'] ?? $line,
        ];
    }
}
