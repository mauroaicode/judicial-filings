<?php

declare(strict_types=1);

namespace Src\Application\Shared\Presence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpIpLocationLookup implements IpLocationLookup
{
    public function locate(string $ip): ?IpLocation
    {
        if (! $this->isPublicIp($ip)) {
            return null;
        }

        $cache = Cache::store((string) config('presence.cache_store'));
        $key = 'ip-location:'.hash('sha256', $ip);
        $cached = $cache->get($key);

        if ($cached === 'none') {
            return null;
        }

        if (is_array($cached)) {
            return new IpLocation(
                country: $this->stringOrNull($cached['country'] ?? null),
                region: $this->stringOrNull($cached['region'] ?? null),
                city: $this->stringOrNull($cached['city'] ?? null),
            );
        }

        try {
            $response = Http::timeout((int) config('presence.geolocation_timeout', 3))
                ->acceptJson()
                ->get(rtrim((string) config('presence.geolocation_url'), '/').'/'.rawurlencode($ip));
        } catch (Throwable $exception) {
            Log::warning('No se pudo consultar la ubicación de la IP.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $cache->put($key, 'none', now()->addHour());

            return null;
        }

        $location = new IpLocation(
            country: $this->stringOrNull($response->json('country')),
            region: $this->stringOrNull($response->json('region')),
            city: $this->stringOrNull($response->json('city')),
        );

        if ($location->isEmpty()) {
            $cache->put($key, 'none', now()->addHour());

            return null;
        }

        $cache->put($key, [
            'country' => $location->country,
            'region' => $location->region,
            'city' => $location->city,
        ], now()->addDays(7));

        return $location;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
