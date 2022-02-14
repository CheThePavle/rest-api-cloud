<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// Регистрация
Route::post('/registration', [\App\Http\Controllers\UserController::class, 'registration']);

// Авторизация
Route::post('/authorization', [\App\Http\Controllers\UserController::class, 'authorization']);

// Авторизация
Route::post('/logout', [\App\Http\Controllers\UserController::class, 'logout']);

// Создание папки
Route::post('/folders', [\App\Http\Controllers\ObjectController::class, 'createFolder']);

// Редактирование папки
Route::post('/folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'changeFolder']);

// Удаление папки
Route::delete('/folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'deleteFolder']);
