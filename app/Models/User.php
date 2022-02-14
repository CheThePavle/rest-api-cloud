<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Str;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'api_token',
    ];

    public function genToken() {
        $this->api_token = Str::random();
        $this->save();
        return $this->api_token;
    }

    public function out() {
        $this->api_token = NULL;
        $this->save();
    }

    public function check($token) {
        $findUser = User::where('api_token', '=', $token)->first();
        if (!$findUser) {
            return false;
        }
        return $findUser->id;
    }
}
