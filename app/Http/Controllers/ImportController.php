<?php
 
declare(strict_types=1);
 
namespace App\Http\Controllers;
 
use App\Http\Requests\ImportRequest;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
 
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
    ) {
    }
 
    public function store(ImportRequest $request): JsonResponse
    {
        $import = $this->importService->handle($request->validated());
 
        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => $import->status->value,
            ],
        ], 202);
    }
 
    public function show(Import $import): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $import->id,
                'supplier' => $import->supplier->code,
                'external_import_id' => $import->external_import_id,
                'sent_at' => $import->sent_at->toIso8601String(),
                'status' => $import->status->value,
                'total_offers' => $import->total_offers,
                'processed_offers' => $import->processed_offers,
                'error' => $import->error,
                'created_at' => $import->created_at->toIso8601String(),
                'completed_at' => $import->completed_at?->toIso8601String(),
            ],
        ]);
    }
}
 