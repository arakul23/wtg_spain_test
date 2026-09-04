<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PropertyService
{
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $rankedOffers = $this->rankedOffersSubquery($filters);

        $query = $this->baseQuery($rankedOffers)
            ->when(
                ! empty($filters['city']),
                fn (Builder $q) => $q->where('properties.city', $filters['city']),
            );

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform($this->mapRow(...));

        return $paginator;
    }

    private function rankedOffersSubquery(array $filters): Builder
    {
        return DB::table('offers')
            ->select([
                'offers.id',
                'offers.property_id',
                'offers.supplier_id',
                'offers.price',
                'offers.currency',
                'offers.available_units',
                'offers.expires_at',
                DB::raw('ROW_NUMBER() OVER (
                    PARTITION BY offers.property_id
                    ORDER BY offers.price ASC
                ) as rn'),
            ])
            ->where('offers.check_in', '<=', $filters['check_in'])
            ->where('offers.check_out', '>=', $filters['check_out'])
            ->where('offers.max_guests', '>=', $filters['guests'])
            ->where('offers.available_units', '>', 0)
            ->where('offers.expires_at', '>', now());
    }

    private function baseQuery(Builder $rankedOffers): Builder
    {
        return DB::table('properties')
            ->joinSub($rankedOffers, 'best_offers', function ($join): void {
                $join->on('properties.id', '=', 'best_offers.property_id')
                    ->where('best_offers.rn', '=', 1);
            })
            ->join('suppliers', 'suppliers.id', '=', 'best_offers.supplier_id')
            ->select([
                'properties.code',
                'properties.name',
                'properties.city',
                'best_offers.id as offer_id',
                'suppliers.code as supplier_code',
                'best_offers.price',
                'best_offers.currency',
                'best_offers.available_units',
                'best_offers.expires_at',
            ])
            ->orderBy('best_offers.price');
    }

    private function mapRow(object $row): array
    {
        return [
            'code' => $row->code,
            'name' => $row->name,
            'city' => $row->city,
            'best_offer' => [
                'id' => $row->offer_id,
                'supplier' => $row->supplier_code,
                'price' => $row->price,
                'currency' => $row->currency,
                'available_units' => $row->available_units,
                'expires_at' => $row->expires_at,
            ],
        ];
    }
}