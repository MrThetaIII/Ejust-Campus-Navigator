<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampusBoundary extends Model
{
    use HasFactory;

    // Add to fillable array
    protected $fillable = ['north', 'south', 'east', 'west', 'location_code'];

    // Add relationship
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }
    protected $casts = [
        'north' => 'float',
        'south' => 'float',
        'east' => 'float',
        'west' => 'float',
    ];
}
