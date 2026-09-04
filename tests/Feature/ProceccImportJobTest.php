<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessImportJobTest extends TestCase
{
    use RefreshDatabase;

    private function importWithOffers(Supplier $supplier, array $offers, string $externalImportId = 'import-001'): Import
    {
        return Import::factory()->create([
            'supplier_id' => $supplier->id,
            'external_import_id' => $externalImportId,
            'status' => ImportStatus::PENDING,
            'total_offers' => count($offers),
            'payload' => [
                'supplier' => $supplier->code,
                'external_import_id' => $externalImportId,
                'sent_at' => '2026-09-01T10:00:00Z',
                'offers' => $offers,
            ],
        ]);
    }

    private function offerPayload(array $overrides = []): array
    {
        return array_merge([
            'external_id' => 'offer-a-10001',
            'property' => [
                'code' => 'BCN-0001',
                'name' => 'Apartment near Sagrada Familia',
                'city' => 'Barcelona',
            ],
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => '2026-12-10T23:59:59Z',
        ], $overrides);
    }

    public function test_it_creates_property_and_offer(): void
    {
        $supplier = Supplier::factory()->create();
        $import = $this->importWithOffers($supplier, [$this->offerPayload()]);

        (new ProcessImportJob($import))->handle();

        $this->assertDatabaseHas('properties', [
            'code' => 'BCN-0001',
            'name' => 'Apartment near Sagrada Familia',
            'city' => 'Barcelona',
        ]);

        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'external_id' => 'offer-a-10001',
            'price' => 72500,
        ]);
    }

    public function test_it_marks_import_as_completed(): void
    {
        $supplier = Supplier::factory()->create();
        $import = $this->importWithOffers($supplier, [$this->offerPayload()]);

        (new ProcessImportJob($import))->handle();

        $import->refresh();

        $this->assertSame(ImportStatus::COMPLETED, $import->status);
        $this->assertSame(1, $import->processed_offers);
        $this->assertNotNull($import->completed_at);
    }

    public function test_it_reuses_existing_property_by_code(): void
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create(['code' => 'BCN-0001']);

        $import = $this->importWithOffers($supplier, [$this->offerPayload()]);

        (new ProcessImportJob($import))->handle();

        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseHas('offers', [
            'property_id' => $property->id,
            'external_id' => 'offer-a-10001',
        ]);
    }

    public function test_it_updates_existing_offer_from_a_different_import(): void
    {
        $supplier = Supplier::factory()->create();

        $firstImport = $this->importWithOffers(
            $supplier,
            [$this->offerPayload(['price' => 72500])],
            'import-001',
        );
        (new ProcessImportJob($firstImport))->handle();

        $secondImport = $this->importWithOffers(
            $supplier,
            [$this->offerPayload(['price' => 65000, 'available_units' => 1])],
            'import-002',
        );
        (new ProcessImportJob($secondImport))->handle();

        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'external_id' => 'offer-a-10001',
            'price' => 65000,
            'available_units' => 1,
        ]);
    }

    public function test_same_external_id_with_different_supplier_creates_separate_offer(): void
    {
        $supplierA = Supplier::factory()->create(['code' => 'supplier-a']);
        $supplierB = Supplier::factory()->create(['code' => 'supplier-b']);

        $importA = $this->importWithOffers($supplierA, [$this->offerPayload()], 'import-a-001');
        (new ProcessImportJob($importA))->handle();

        $importB = $this->importWithOffers($supplierB, [$this->offerPayload(['price' => 99999])], 'import-b-001');
        (new ProcessImportJob($importB))->handle();

        $this->assertDatabaseCount('offers', 2);
        $this->assertDatabaseHas('offers', ['supplier_id' => $supplierA->id, 'external_id' => 'offer-a-10001', 'price' => 72500]);
        $this->assertDatabaseHas('offers', ['supplier_id' => $supplierB->id, 'external_id' => 'offer-a-10001', 'price' => 99999]);
    }

    public function test_it_marks_import_as_failed_on_exception(): void
    {
        $supplier = Supplier::factory()->create();

        $import = $this->importWithOffers($supplier, [
            [
                'external_id' => 'offer-broken',
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 4,
                'price' => 72500,
                'currency' => 'EUR',
                'available_units' => 2,
                'expires_at' => '2026-12-10T23:59:59Z',
            ],
        ]);

        (new ProcessImportJob($import))->handle();

        $import->refresh();

        $this->assertSame(ImportStatus::FAILED, $import->status);
        $this->assertNotNull($import->error);
        $this->assertNotNull($import->completed_at);
    }

    public function test_it_does_not_reprocess_already_processed_import(): void
    {
        $supplier = Supplier::factory()->create();
        $import = $this->importWithOffers($supplier, [$this->offerPayload()]);
        $import->update(['status' => ImportStatus::COMPLETED]);

        (new ProcessImportJob($import))->handle();

        $this->assertDatabaseCount('offers', 0);
    }
}