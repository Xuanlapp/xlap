<?php

namespace App\Services\Flight\Providers;

interface FlightProviderInterface
{
    /**
     * Search one-way flights for a route/date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $origin, string $destination, string $departureDate): array;
}
