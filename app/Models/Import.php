<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Offer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'external_import_id',
        'sent_at',
        'status',
        'total_offers',
        'processed_offers',
        'error',
        'payload',
        'completed_at',
    ];
 
    protected $casts = [
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => ImportStatus::class,
        'payload' => 'array',
        'total_offers' => 'integer',
        'processed_offers' => 'integer',
    ];
 
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
 
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
