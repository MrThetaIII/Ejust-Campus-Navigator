<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function hops()
    {
        return $this->hasMany(Hop::class, 'location_code', 'code');
    }

    public function connections()
    {
        return $this->hasMany(RoadConnection::class, 'location_code', 'code');
    }

    public function boundaries()
    {
        return $this->hasMany(CampusBoundary::class, 'location_code', 'code');
    }

    public function overlays()
    {
        return $this->hasMany(Overlay::class, 'location_code', 'code');
    }
}
