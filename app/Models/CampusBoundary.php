<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampusBoundary extends Model
{
    use HasFactory;

    protected $fillable = ['north', 'south', 'east', 'west'];

    protected $casts = [
        'north' => 'float',
        'south' => 'float',
        'east' => 'float',
        'west' => 'float',
    ];
}
