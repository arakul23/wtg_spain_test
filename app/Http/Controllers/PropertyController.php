<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PropertyRequest;
use App\Services\PropertyService;
use Illuminate\Http\JsonResponse;

class PropertyController extends Controller
{
    public function __construct(
        private readonly PropertyService $propertyService,
    ) {
    }

    public function index(PropertyRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);

        $paginator = $this->propertyService->search(
            $request->validated(),
            $perPage,
        );

        return response()->json([
            'data' => $paginator->items(),
            'per_page' => $paginator->perPage(),
            'next' => $paginator->nextPageUrl(),
            'prev' => $paginator->previousPageUrl(),
        ]);
    }
}