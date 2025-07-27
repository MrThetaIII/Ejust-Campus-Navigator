<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overlay extends Model
{
    use HasFactory;

    // Add to fillable array
    protected $fillable = ['name', 'image_path', 'corners', 'opacity', 'active', 'location_code'];

    // Add relationship
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }
    protected $casts = [
        'corners' => 'array',
        'opacity' => 'float',
        'active' => 'boolean',
    ];
}
