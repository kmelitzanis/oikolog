<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'nutrition', 'image_path', 'created_by'];

    protected $casts = [
        'nutrition' => 'array',
    ];
}


