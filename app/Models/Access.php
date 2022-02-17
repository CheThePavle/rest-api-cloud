<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;


class Access extends Model
{
    use HasFactory;

    protected $table = 'accesses';

    protected $fillable = [
        'id_object',
        'id_co_author'
    ];
}
