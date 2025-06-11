<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get(uri: '/user', action: function (Request $request): User {
    return $request->user();
})->middleware(middleware: 'auth:sanctum');
