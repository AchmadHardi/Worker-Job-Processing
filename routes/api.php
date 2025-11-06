<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NotificationController;

Route::post('/notifications', [NotificationController::class, 'enqueue']);
Route::get('/internal/queue/stats', [NotificationController::class, 'stats']);
