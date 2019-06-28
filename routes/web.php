<?php

use Illuminate\Support\Facades\Route;

Route::get('payment/{id}', 'PaymentController@show')->name('payment');
Route::post('webhook', 'WebhookController@handleWebhook')->name('webhook');
