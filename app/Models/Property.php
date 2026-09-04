<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city',
    ];
 
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
