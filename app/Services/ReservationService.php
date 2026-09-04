<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\OfferNotAvailableException;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function create(Offer $offer, array $data): Reservation
    {
        return DB::transaction(function () use ($offer, $data): Reservation {
            $locked = Offer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($locked->available_units < 1) {
                throw new OfferNotAvailableException();
            }

            $locked->decrement('available_units');

            return Reservation::create([
                'offer_id' => $locked->id,
                'client_reference' => $data['client_reference'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'status' => ReservationStatus::CONFIRMED,
            ]);
        });
    }
}