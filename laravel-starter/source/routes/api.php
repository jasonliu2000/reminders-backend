<?php

use App\Controllers\API\UserController;
use App\Controllers\API\ReminderController;
use Illuminate\Support\Facades\Route;

/**
 * Use this file to define new API routes under the /api/... path
 * 
 * Here are some example, user related endpoints we have established as an example
 */

Route::get('/users/{id}', [UserController::class, 'read']);
Route::post('/users', [UserController::class, 'create']);

Route::get('/reminders/{id}', [ReminderController::class, 'getById']);
Route::get('/reminders/search', [ReminderController::class, 'searchRemindersByKeyword']); // TODO: change to get prefix?
Route::get('/reminders', [ReminderController::class, 'getRemindersInDateRange']);
Route::delete('/reminders/{id}', [ReminderController::class, 'delete']);
