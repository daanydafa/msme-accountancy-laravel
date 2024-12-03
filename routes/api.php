<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('firebase.auth')->group(function () {
    Route::middleware(['is.admin'])->group(function () {
        Route::post('/users', [UserController::class, 'create']);
        Route::get('/users', [UserController::class, 'index']);
        Route::delete('/users/{id}', [UserController::class, 'delete']);
        Route::delete('/orders', [OrderController::class, 'deleteFromList']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', [UserController::class, 'getData']);

    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'create']);
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/ongoing', [OrderController::class, 'getOnGoingOrders']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::put('/', [OrderController::class, 'edit']);
    });

    Route::prefix('transactions')->group(function () {
        Route::post('/', [TransactionController::class, 'create']);
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{month}', [TransactionController::class, 'getTransactionsByMonth']);
        Route::post('/list', [TransactionController::class, 'getTransactionsByList']);
        Route::put('/', [TransactionController::class, 'edit']);
        Route::put('/reimbursement_status', [TransactionController::class, 'updateReimbursementStatus']);
    });

    Route::get('reports/{month}/{year?}', [ReportController::class, 'getMonthlyReport']);
});
