<?php

namespace App\Livewire\Customers;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bulk-import customers from a CSV. The first row is treated as a header;
 * columns whose names match a fillable `clients` field are imported, the
 * rest are ignored. A row needs at least a first name or an email to count.
 */
class Import extends Component
{
    use WithFileUploads;

    public $file;

    public ?int $imported = null;

    public ?int $skipped = null;

    public ?string $error = null;

    /** @var list<string> */
    public array $preview = [];

    protected function rules(): array
    {
        return ['file' => 'required|file|mimes:csv,txt|max:5120'];
    }

    public function updatedFile(): void
    {
        $this->reset('imported', 'skipped', 'error', 'preview');

        try {
            $this->validateOnly('file');
            $rows = $this->rows();
            $this->preview = array_slice(array_keys($rows[0] ?? []), 0, 30);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function import(): void
    {
        $this->validate();
        $this->reset('imported', 'skipped', 'error');

        $fillable = (new Client)->getFillable();
        $rows = $this->rows();
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $fillable, &$imported, &$skipped) {
            foreach ($rows as $row) {
                $data = array_intersect_key($row, array_flip($fillable));
                $data = array_filter($data, fn ($v) => $v !== '' && $v !== null);

                if (empty($data['first_name']) && empty($data['email'])) {
                    $skipped++;

                    continue;
                }

                Client::create($data);
                $imported++;
            }
        });

        $this->imported = $imported;
        $this->skipped = $skipped;
        $this->reset('file', 'preview');
    }

    /** @return list<array<string, string>> header-keyed rows */
    private function rows(): array
    {
        $path = $this->file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($h) => str_replace(' ', '_', strtolower(trim((string) $h))), $header);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), null));
        }
        fclose($handle);

        return $rows;
    }

    public function render(): View
    {
        return view('livewire.customers.import')->layout('layouts.app', ['title' => 'Import Customers']);
    }
}
