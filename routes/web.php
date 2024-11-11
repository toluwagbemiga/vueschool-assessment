<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserUpdateController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/update-users', [UserUpdateController::class, 'updateUsers']);