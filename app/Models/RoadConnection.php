<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoadConnection extends Model
{
    use HasFactory;

    protected $fillable = ['hop_from_id', 'hop_to_id', 'name', 'width', 'color', 'distance'];

    protected $casts = [
        'distance' => 'float',
        'width' => 'integer',
    ];

    public function hopFrom()
    {
        return $this->belongsTo(Hop::class, 'hop_from_id');
    }

    public function hopTo()
    {
        return $this->belongsTo(Hop::class, 'hop_to_id');
    }

    public function calculateDistance()
    {
        $from = $this->hopFrom;
        $to = $this->hopTo;

        $earthRadius = 6371000; // meters
        $lat1 = deg2rad($from->latitude);
        $lat2 = deg2rad($to->latitude);
        $deltaLat = deg2rad($to->latitude - $from->latitude);
        $deltaLon = deg2rad($to->longitude - $from->longitude);

        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon/2) * sin($deltaLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}
