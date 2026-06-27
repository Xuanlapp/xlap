<?php

namespace App\Services\Flight;

use App\Services\Flight\Providers\FlightProviderInterface;
use App\Services\Flight\Providers\MockFlightProvider;

class FlightSearchService
{
    public function __construct(
        private readonly MockFlightProvider $mockProvider,
    ) {}

    /**
     * Search flights through the configured provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $origin, string $destination, string $departureDate): array
    {
        return $this->provider()->search($origin, $destination, $departureDate);
    }

    /**
     * Resolve the active flight provider.
     */
    private function provider(): FlightProviderInterface
    {
        return $this->mockProvider;
    }
}
