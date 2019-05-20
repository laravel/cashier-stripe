<?php

use Illuminate\Support\Facades\Route;

Route::post('webhook', 'WebhookController@handleWebhook')->name('webhook');
