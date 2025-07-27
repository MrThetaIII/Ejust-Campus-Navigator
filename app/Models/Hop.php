<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hop extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'name', 'description', 'latitude', 'longitude', 'icon', 'image_path', 'location_code'];


    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function connectionsFrom()
    {
        return $this->hasMany(RoadConnection::class, 'hop_from_id');
    }

    public function connectionsTo()
    {
        return $this->hasMany(RoadConnection::class, 'hop_to_id');
    }

    public function allConnections()
    {
        return $this->connectionsFrom->merge($this->connectionsTo);
    }

    public function getConnectedHops()
    {
        $fromHops = $this->connectionsFrom->pluck('hopTo');
        $toHops = $this->connectionsTo->pluck('hopFrom');
        return $fromHops->merge($toHops);
    }
}
