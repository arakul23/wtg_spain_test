<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'client_reference',
        'customer_name',
        'customer_email',
        'status',
    ];

    protected $casts = [
        'status' => ReservationStatus::class,
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}