<?php

use App\Controllers\API\UserController;
use App\Controllers\API\ReminderController;
use Illuminate\Support\Facades\Route;

Route::get('/users/{id}', [UserController::class, 'read']);
Route::post('/users', [UserController::class, 'create']);

Route::get('/reminders/search', [ReminderController::class, 'searchRemindersByKeyword']); // TODO: change to get prefix?
Route::get('/reminders', [ReminderController::class, 'getRemindersInDateRange']);
Route::get('/reminders/{id}', [ReminderController::class, 'getById']);
Route::post('/reminders', [ReminderController::class, 'create']);
Route::patch('/reminders/{id}', [ReminderController::class, 'patch']);
Route::delete('/reminders/{id}', [ReminderController::class, 'delete']);
