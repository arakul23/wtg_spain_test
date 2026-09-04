<?php
 
declare(strict_types=1);
 
namespace App\Http\Controllers;
 
use App\Http\Requests\ImportRequest;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ImportCreatedResource;
use App\Http\Resources\ImportResource;
 
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
    ) {
    }
 
    public function store(ImportRequest $request): JsonResponse
    {
        $import = $this->importService->handle($request->validated());
 
        return (new ImportCreatedResource($import))
                ->response()
                ->setStatusCode(202);
    }
 
    public function show(Import $import): JsonResponse
    {
         return (new ImportResource($import))->response();
    }
}
 