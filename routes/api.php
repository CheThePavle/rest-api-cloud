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


// Авторизация
Route::post('authorization', [\App\Http\Controllers\UserController::class, 'authorization']);

// Регистрация
Route::post('registration', [\App\Http\Controllers\UserController::class, 'registration']);


Route::middleware('checkAuth')->group(function () {
    // Выход
    Route::post('logout', [\App\Http\Controllers\UserController::class, 'logout']);

    // Создание папки
    Route::post('folders', [\App\Http\Controllers\ObjectController::class, 'createFolder']);

    // Редактирование папки
    Route::patch('folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'changeFolder']);
    Route::put('folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'changeFolder']);

    // Удаление папки
    Route::delete('folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'deleteFolder']);

    // Загрузка файлов
    Route::post('folders/{folder_id}/files', [\App\Http\Controllers\ObjectController::class, 'uploadeFiles']);

    // Редактирование файла
    Route::patch('folders/{folder_id}/files/{file_id}', [\App\Http\Controllers\ObjectController::class, 'changeFile']);
    Route::put('folders/{folder_id}/files/{file_id}', [\App\Http\Controllers\ObjectController::class, 'changeFile']);

    // Удаление файла
    Route::delete('folders/{folder_id}/files/{file_id}', [\App\Http\Controllers\ObjectController::class, 'deleteFile']);

    // Просмотр папки
    Route::get('folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'viewFolder']);

    // Скачивание файла
    Route::get('file/{file_id}', [\App\Http\Controllers\ObjectController::class, 'downloadFile']);

    // Добавление прав доступа
    Route::post('folders/{folder_id}/files/{file_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'addAccessFile']);
    Route::post('folders/{folder_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'addAccessFolder']);

    // Удаление прав доступа
    Route::delete('folders/{folder_id}/files/{file_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'deleteAccessFile']);
    Route::delete('folders/{folder_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'deleteAccessFolder']);

    // Просмотр папок и файлов пользователя
    Route::get('disk', [\App\Http\Controllers\AccessesController::class, 'disk']);

    // Просмотр папок и файлов разрешенных пользователю
    Route::get('shared', [\App\Http\Controllers\AccessesController::class, 'shared']);
});
