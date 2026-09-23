<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventAuditController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventReportController;
use App\Http\Controllers\EventSearchController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\SystemAlertController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/events/search', [EventSearchController::class, 'index']);
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::post('/events/{event}/seats', [SeatController::class, 'store']);
    Route::get('/events/{event}/seats', [SeatController::class, 'index']);
    Route::get('/events/{event}/report', [EventReportController::class, 'show']);
    Route::get('/events/{event}/audit', [EventAuditController::class, 'index']);

    Route::post('/events/{event}/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
    Route::post('/reservations/{reservation}/confirm', [ReservationController::class, 'confirm']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/tickets/{ticket}/reissue', [TicketController::class, 'reissue']);

    Route::get('/system/alerts', [SystemAlertController::class, 'index']);
    Route::get('/ops/metrics', [OperationsController::class, 'metrics']);
});
