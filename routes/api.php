
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NinVerificationController;

Route::prefix('nin/verification')
    ->controller(NinVerificationController::class)
    ->middleware('throttle:10,1')
    ->group(function () {

        // Start NIN verification
        Route::post('/start', 'start');

        // Submit answer and get next question
        Route::post('/answer', 'answer');

        // Retrieve personal information after verification
        Route::post('/person', 'person');

    });

// Staff NIDA profiling: lookup ya moja kwa moja kwa NIN (API key inahitajika)
Route::post('nin/profile', [NinVerificationController::class, 'profile'])
    ->middleware('throttle:60,1');
