<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Database\QueryException;

class ImportService
{
    public function handle(array $data): Import
    {
        $supplier = Supplier::where('code', $data['supplier'])->firstOrFail();
       

        $import = $this->firstOrCreateImport($supplier, $data);

        if ($import->wasRecentlyCreated) {
            ProcessImportJob::dispatch($import);
        }

        return $import;
    }

    private function firstOrCreateImport(Supplier $supplier, array $data): Import
    {
        $attributes = [
            'supplier_id' => $supplier->id,
            'external_import_id' => $data['external_import_id'],
        ];

        try {
            return Import::firstOrCreate($attributes, [
                'sent_at' => $data['sent_at'],
                'status' => ImportStatus::PENDING,
                'total_offers' => count($data['offers']),
                'payload' => $data,
            ]);
        } catch (QueryException) {
            return Import::where($attributes)->firstOrFail();
        }
    }
}