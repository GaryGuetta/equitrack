<?php

namespace App\Weather;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Prévisions sur 12 h via l'API Open-Meteo (gratuite, sans clé), mises en cache 30 min.
 */
class WeatherProvider
{
    private const ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'bool:WEATHER_API_ENABLED')]
        private readonly bool $enabled,
    ) {
    }

    public function forecast(float $latitude, float $longitude): ?Forecast
    {
        if (!$this->enabled) {
            return null;
        }

        // Arrondi à ~1 km : les chevaux d'une même écurie partagent la même entrée de cache.
        $key = \sprintf('forecast_%.2f_%.2f', $latitude, $longitude);

        return $this->cache->get($key, function (ItemInterface $item) use ($latitude, $longitude): ?Forecast {
            $item->expiresAfter(1800);
            try {
                $data = $this->httpClient->request('GET', self::ENDPOINT, [
                    'timeout' => 4,
                    'query' => [
                        'latitude' => round($latitude, 3),
                        'longitude' => round($longitude, 3),
                        'current' => 'temperature_2m,wind_speed_10m',
                        'hourly' => 'temperature_2m,precipitation_probability',
                        'forecast_hours' => 12,
                        'timezone' => 'Europe/Paris',
                    ],
                ])->toArray();

                return new Forecast(
                    (float) $data['current']['temperature_2m'],
                    (float) min($data['hourly']['temperature_2m']),
                    (int) max($data['hourly']['precipitation_probability']),
                    (float) $data['current']['wind_speed_10m'],
                );
            } catch (ExceptionInterface|\TypeError|\ValueError $e) {
                $this->logger->warning('Prévisions météo indisponibles : {message}', ['message' => $e->getMessage()]);
                $item->expiresAfter(300);

                return null;
            }
        });
    }
}
