<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accesse extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_object',
        'id_co_author'
    ];
}
