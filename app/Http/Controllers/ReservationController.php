<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OfferNotAvailableException;
use App\Http\Requests\ReservationRequest;
use App\Models\Offer;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ReservationResource;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {
    }

    public function store(ReservationRequest $request, Offer $offer): JsonResponse
    {
        try {
            $reservation = $this->reservationService->create($offer, $request->validated());
        } catch (OfferNotAvailableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return (new ReservationResource($reservation))
                ->response()
                ->setStatusCode(202);
                
    }
}