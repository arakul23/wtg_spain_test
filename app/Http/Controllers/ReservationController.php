<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OfferNotAvailableException;
use App\Http\Requests\ReservationRequest;
use App\Models\Offer;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

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

        return response()->json([
            'data' => [
                'id' => $reservation->id,
                'offer_id' => $reservation->offer_id,
                'client_reference' => $reservation->client_reference,
                'customer_name' => $reservation->customer_name,
                'customer_email' => $reservation->customer_email,
                'status' => $reservation->status->value,
                'created_at' => $reservation->created_at->toIso8601String(),
            ],
        ], 201);
    }
}