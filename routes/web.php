<?php
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/weather', [WeatherController::class, 'current']);

// Route::middleware('api')->group(function () {
//     // Route::get('/weather', [WeatherController::class, 'current']);
//     return response()->json([
//         'message' => 'Current weather data goes here',
//     ]);
// });

Route::middleware('api')->group(function () {
    Route::get('/weather', function () {
        return response()->json([
            'message' => 'Current weather data goes here',
        ]);
    });
});
