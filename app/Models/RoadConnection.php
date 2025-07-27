<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoadConnection extends Model
{
    use HasFactory;

    protected $fillable = ['hop_from_id', 'hop_to_id', 'name', 'width', 'color', 'distance', 'location_code'];

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

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function calculateDistance()
    {
        try {
            $from = $this->hopFrom;
            $to = $this->hopTo;

            if (!$from || !$to) {
                \Log::warning('Cannot calculate distance - missing hop data', [
                    'connection_id' => $this->id,
                    'hop_from_id' => $this->hop_from_id,
                    'hop_to_id' => $this->hop_to_id
                ]);
                return 1; // Default distance
            }

            $earthRadius = 6371000; // meters
            $lat1 = deg2rad($from->latitude);
            $lat2 = deg2rad($to->latitude);
            $deltaLat = deg2rad($to->latitude - $from->latitude);
            $deltaLon = deg2rad($to->longitude - $from->longitude);

            $a = sin($deltaLat/2) * sin($deltaLat/2) +
                 cos($lat1) * cos($lat2) *
                 sin($deltaLon/2) * sin($deltaLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-a));

            $distance = $earthRadius * $c;

            // Ensure minimum distance
            return max($distance, 1);

        } catch (\Exception $e) {
            \Log::error('Error calculating distance', [
                'error' => $e->getMessage(),
                'connection_id' => $this->id ?? 'new'
            ]);
            return 1; // Default distance
        }
    }
}
