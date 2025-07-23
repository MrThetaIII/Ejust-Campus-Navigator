<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overlay extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'image_path', 'corners', 'opacity', 'active'];

    protected $casts = [
        'corners' => 'array',
        'opacity' => 'float',
        'active' => 'boolean',
    ];
}
