<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WeatherController extends Controller
{
    protected WeatherService $weatherService;

    public function __construct(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }
    /**
     * Get current weather data
     *
     * @OA\Get(
     *     path="/api/weather",
     *     tags={"Weather"},
     *     summary="Get current weather data",
     *     description="Returns current weather data for a location",
     *     @OA\Parameter(
     *         name="lat",
     *         in="query",
     *         description="Latitude (required with lon)",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="lon",
     *         in="query",
     *         description="Longitude (required with lat)",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="City name (required if lat/lon not provided)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/WeatherData")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function getCurrentWeather(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat' => 'required_with:lon|numeric|between:-90,90',
                'lon' => 'required_with:lat|numeric|between:-180,180',
                'city' => 'required_without_all:lat,lon|string|max:100',
                'units' => 'sometimes|string|in:metric,imperial',
            ]);

            $weather = $this->weatherService->getCurrentWeather(
                $validated['lat'] ?? null,
                $validated['lon'] ?? null,
                $validated['city'] ?? null,
                $validated['units'] ?? 'metric'
            );

            return response()->json([
                'success' => true,
                'data' => $weather,
                'message' => 'Weather data retrieved successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 400);

        } catch (\InvalidArgumentException $e) {
            Log::error('Weather API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            Log::error('Weather API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve weather data'
            ], 500);
        }
    }

    /**
     * Get weather forecast data
     *
     * @OA\Get(
     *     path="/api/weather/forecast",
     *     tags={"Weather"},
     *     summary="Get weather forecast data",
     *     description="Returns weather forecast data for a location",
     *     @OA\Parameter(
     *         name="lat",
     *         in="query",
     *         description="Latitude (required with lon)",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="lon",
     *         in="query",
     *         description="Longitude (required with lat)",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="City name (required if lat/lon not provided)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of forecast days (max 5 for free tier)",
     *         required=false,
     *         @OA\Schema(type="integer", default=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/WeatherForecast")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function getWeatherForecast(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat' => 'required_with:lon|numeric|between:-90,90',
                'lon' => 'required_with:lat|numeric|between:-180,180',
                'city' => 'required_without_all:lat,lon|string|max:100',
                'days' => 'sometimes|integer|min:1|max:5',
                'units' => 'sometimes|string|in:metric,imperial',
            ]);

            $forecast = $this->weatherService->getWeatherForecast(
                $validated['lat'] ?? null,
                $validated['lon'] ?? null,
                $validated['city'] ?? null,
                $validated['days'] ?? 3,
                $validated['units'] ?? 'metric'
            );

            return response()->json([
                'success' => true,
                'data' => $forecast,
                'message' => 'Weather forecast retrieved successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 400);

        } catch (\Exception $e) {
            Log::error('Weather Forecast API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve weather forecast'
            ], 500);
        }
    }
}