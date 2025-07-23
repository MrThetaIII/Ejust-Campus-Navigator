<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Road extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'path', 'width', 'color'];

    protected $casts = [
        'path' => 'array',
        'width' => 'integer',
    ];
}
