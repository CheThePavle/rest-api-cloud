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

// Регистрация
Route::post('/registration', [\App\Http\Controllers\UserController::class, 'registration']);

// Авторизация
Route::post('/authorization', [\App\Http\Controllers\UserController::class, 'authorization']);

Route::middleware('checkAuth')->group(function () {
    // Выход
    Route::post('/logout', [\App\Http\Controllers\UserController::class, 'logout']);

    // Создание папки
    Route::post('/folders', [\App\Http\Controllers\ObjectController::class, 'createFolder']);

    // Редактирование папки
    Route::patch('/folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'changeFolder']);

    // Удаление папки
    Route::delete('/folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'deleteFolder']);

    // Загрузка файлов
    Route::post('/folders/{folder_id}/files', [\App\Http\Controllers\ObjectController::class, 'uploadedFiles']);

    // Редактирование файла
    Route::patch('/folders/{folder_id}/files/{file_id}', [\App\Http\Controllers\ObjectController::class, 'changeFile']);

    // Удаление файла
    Route::delete('/folders/{folder_id}/files/{file_id}', [\App\Http\Controllers\ObjectController::class, 'deleteFile']);

    // Просмотр папки
    Route::get('/folders/{folder_id}', [\App\Http\Controllers\ObjectController::class, 'showFolder']);

    // Скачивание файла
    Route::get('/file/{file_id}', [\App\Http\Controllers\ObjectController::class, 'downloadFile']);

    // Добавление прав доступа файлу
    Route::post('/folders/{folder_id}/files/{file_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'addAccessFile']);
    // Добавление прав доступа папке
    Route::post('/folders/{folder_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'addAccessFolder']);

    // Удаление прав доступа файлу
    Route::delete('/folders/{folder_id}/files/{file_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'deleteAccessFile']);
    // Удаление прав доступа папке
    Route::delete('/folders/{folder_id}/accesses', [\App\Http\Controllers\AccessesController::class, 'deleteAccessFolder']);

    // Просмотр папок и файлов пользователя
    Route::get('/disk', [\App\Http\Controllers\AccessesController::class, 'myFolderAndFiles']);

    // Просмотр папок и файлов, которые доступны текущему пользователю
    Route::get('/shared', [\App\Http\Controllers\AccessesController::class, 'accessesFolderAndFiles']);
});
