<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

class WeatherService
{
    protected Client $client;
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.openweathermap.key');
        $this->baseUrl = config('services.openweathermap.url');
    }

    /**
     * Get current weather data
     */
    public function getCurrentWeather(?float $lat = null, ?float $lon = null, ?string $city = null, string $units = 'metric'): array
    {
        $cacheKey = $this->generateCacheKey('current', $lat, $lon, $city, $units);
        
        return Cache::remember($cacheKey, now()->addHour(), function() use ($lat, $lon, $city, $units) {
            $params = $this->buildBaseParams($units);
            
            if ($lat && $lon) {
                $params['lat'] = $lat;
                $params['lon'] = $lon;
            } elseif ($city) {
                $params['q'] = $city;
            } else {
                throw new \InvalidArgumentException('Either lat/lon or city must be provided');
            }

            $response = $this->client->get("{$this->baseUrl}/weather", [
                'query' => $params
            ]);

            $data = json_decode($response->getBody(), true);
            return $this->formatCurrentWeatherData($data);
        });
    }

    /**
     * Get weather forecast data
     */
    public function getWeatherForecast(?float $lat = null, ?float $lon = null, ?string $city = null, int $days = 3, string $units = 'metric'): array
    {
        $cacheKey = $this->generateCacheKey('forecast', $lat, $lon, $city, $units, $days);
        
        return Cache::remember($cacheKey, now()->addHours(3), function() use ($lat, $lon, $city, $days, $units) {
            $params = $this->buildBaseParams($units);
            $params['cnt'] = $days * 8; // 3-hour intervals per day
            
            if ($lat && $lon) {
                $params['lat'] = $lat;
                $params['lon'] = $lon;
            } elseif ($city) {
                $params['q'] = $city;
            } else {
                throw new \InvalidArgumentException('Either lat/lon or city must be provided');
            }

            $response = $this->client->get("{$this->baseUrl}/forecast", [
                'query' => $params
            ]);

            $data = json_decode($response->getBody(), true);
            return $this->formatForecastData($data, $days);
        });
    }

    protected function buildBaseParams(string $units): array
    {
        return [
            'appid' => $this->apiKey,
            'units' => $units,
            'lang' => 'en',
        ];
    }

    protected function generateCacheKey(string $type, ...$args): string
    {
        return 'weather:' . $type . ':' . md5(implode('|', array_filter($args)));
    }

    protected function formatCurrentWeatherData(array $data): array
    {
        return [
            'temp' => $data['main']['temp'],
            'feels_like' => $data['main']['feels_like'],
            'temp_min' => $data['main']['temp_min'],
            'temp_max' => $data['main']['temp_max'],
            'humidity' => $data['main']['humidity'],
            'pressure' => $data['main']['pressure'],
            'wind_speed' => $data['wind']['speed'],
            'wind_deg' => $data['wind']['deg'] ?? null,
            'clouds' => $data['clouds']['all'] ?? 0,
            'visibility' => $data['visibility'] ?? null,
            'conditions' => $data['weather'][0]['main'],
            'description' => $data['weather'][0]['description'],
            'icon' => $data['weather'][0]['icon'],
            'location' => $data['name'],
            'country' => $data['sys']['country'] ?? '',
            'sunrise' => $data['sys']['sunrise'] ?? null,
            'sunset' => $data['sys']['sunset'] ?? null,
            'timezone' => $data['timezone'] ?? 0,
            'dt' => $data['dt'],
            'coord' => $data['coord'] ?? null,
        ];
    }

    protected function formatForecastData(array $data, int $days): array
    {
        $forecast = [
            'city' => $data['city']['name'] ?? '',
            'country' => $data['city']['country'] ?? '',
            'timezone' => $data['city']['timezone'] ?? 0,
            'days' => [],
        ];

        // Group forecast by day
        $dailyData = [];
        foreach ($data['list'] as $item) {
            $date = date('Y-m-d', $item['dt']);
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'date' => $date,
                    'temps' => [],
                    'weather' => [],
                ];
            }
            $dailyData[$date]['temps'][] = $item['main']['temp'];
            $dailyData[$date]['weather'][] = $item['weather'][0];
        }

        // Format daily forecast
        $count = 0;
        foreach ($dailyData as $day) {
            if ($count >= $days) break;
            
            $forecast['days'][] = [
                'date' => $day['date'],
                'temp_avg' => array_sum($day['temps']) / count($day['temps']),
                'temp_min' => min($day['temps']),
                'temp_max' => max($day['temps']),
                'conditions' => $this->getDominantWeather($day['weather']),
                'icon' => $this->getDominantIcon($day['weather']),
            ];
            $count++;
        }

        return $forecast;
    }

    protected function getDominantWeather(array $weatherItems): string
    {
        $weatherCounts = [];
        foreach ($weatherItems as $weather) {
            $main = $weather['main'];
            $weatherCounts[$main] = ($weatherCounts[$main] ?? 0) + 1;
        }
        arsort($weatherCounts);
        return array_key_first($weatherCounts);
    }

    protected function getDominantIcon(array $weatherItems): string
    {
        $iconCounts = [];
        foreach ($weatherItems as $weather) {
            $icon = $weather['icon'];
            $iconCounts[$icon] = ($iconCounts[$icon] ?? 0) + 1;
        }
        arsort($iconCounts);
        return array_key_first($iconCounts);
    }
}