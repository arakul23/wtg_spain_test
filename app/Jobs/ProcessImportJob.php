<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly Import $import,
    ) {
    }

    public function handle(): void
    {
        if ($this->import->status !== ImportStatus::PENDING) {
            return;
        }

        $this->import->update(['status' => ImportStatus::PROCESSING]);

        try {
            $processed = DB::transaction(fn (): int => $this->processOffers());

            $this->import->update([
                'status' => ImportStatus::COMPLETED,
                'processed_offers' => $processed,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Import processing failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            $this->import->update([
                'status' => Status::FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    private function processOffers(): int
    {
        $payload = $this->import->payload;
        $supplierId = $this->import->supplier_id;
        $processed = 0;

        foreach ($payload['offers'] as $offerData) {
            $property = Property::firstOrCreate(
                ['code' => $offerData['property']['code']],
                [
                    'name' => $offerData['property']['name'],
                    'city' => $offerData['property']['city'],
                ],
            );

            Offer::updateOrCreate(
                [
                    'supplier_id' => $supplierId,
                    'external_id' => $offerData['external_id'],
                ],
                [
                    'property_id' => $property->id,
                    'import_id' => $this->import->id,
                    'check_in' => $offerData['check_in'],
                    'check_out' => $offerData['check_out'],
                    'max_guests' => $offerData['max_guests'],
                    'price' => $offerData['price'],
                    'currency' => $offerData['currency'],
                    'available_units' => $offerData['available_units'],
                    'expires_at' => $offerData['expires_at'],
                ],
            );

            $processed++;
        }

        return $processed;
    }
}