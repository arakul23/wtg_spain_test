<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(Supplier $supplier, string $externalImportId = 'import-001'): array
    {
        return [
            'supplier' => $supplier->code,
            'external_import_id' => $externalImportId,
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
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
                ],
            ],
        ];
    }

    public function test_it_creates_import_and_returns_202(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);

        $response = $this->postJson('/api/imports', $this->validPayload($supplier));

        $response->assertStatus(202)
            ->assertJsonPath('data.status', ImportStatus::PENDING->value);

        $this->assertDatabaseHas('imports', [
            'supplier_id' => $supplier->id,
            'external_import_id' => 'import-001',
        ]);
    }

    public function test_it_dispatches_process_import_job(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);

        $this->postJson('/api/imports', $this->validPayload($supplier));

        Queue::assertPushed(ProcessImportJob::class);
    }

    public function test_it_rejects_unknown_supplier(): void
    {
        Queue::fake();

        $payload = $this->validPayload(Supplier::factory()->make(['code' => 'unknown-supplier']));
        $payload['supplier'] = 'unknown-supplier';

        $response = $this->postJson('/api/imports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier']);

        Queue::assertNothingPushed();
    }

    public function test_it_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/imports', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier', 'external_import_id', 'sent_at', 'offers']);
    }

    public function test_it_rejects_offer_with_invalid_structure(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $payload = $this->validPayload($supplier);
        unset($payload['offers'][0]['price']);

        $response = $this->postJson('/api/imports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offers.0.price']);
    }

    public function test_resending_same_import_does_not_create_duplicate(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $payload = $this->validPayload($supplier);

        $this->postJson('/api/imports', $payload)->assertStatus(202);
        $this->postJson('/api/imports', $payload)->assertStatus(202);

        $this->assertDatabaseCount('imports', 1);
    }

    public function test_resending_same_import_does_not_dispatch_job_twice(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $payload = $this->validPayload($supplier);

        $this->postJson('/api/imports', $payload);
        $this->postJson('/api/imports', $payload);

        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_resending_same_import_returns_same_import_id(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $payload = $this->validPayload($supplier);

        $first = $this->postJson('/api/imports', $payload);
        $second = $this->postJson('/api/imports', $payload);

        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
        );
    }

    public function test_show_returns_import_details(): void
    {
        $import = Import::factory()->create([
            'status' => ImportStatus::COMPLETED,
            'total_offers' => 5,
            'processed_offers' => 5,
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.status', ImportStatus::COMPLETED->value)
            ->assertJsonPath('data.total_offers', 5)
            ->assertJsonPath('data.processed_offers', 5);
    }

    public function test_show_returns_404_for_unknown_import(): void
    {
        $response = $this->getJson('/api/imports/999999');

        $response->assertStatus(404);
    }
}