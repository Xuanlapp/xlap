<?php

namespace App\Services\Flight\Providers;

class MockFlightProvider implements FlightProviderInterface
{
    /**
     * Return demo flights until a real provider is configured.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $origin, string $destination, string $departureDate): array
    {
        return [
            [
                'airline' => 'VietJet Air',
                'flight_number' => 'VJ-demo',
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'departure_time' => '06:00',
                'arrival_time' => '08:10',
                'duration_minutes' => 130,
                'price' => 1120000,
                'currency' => 'VND',
                'is_demo' => true,
            ],
            [
                'airline' => 'Bamboo Airways',
                'flight_number' => 'QH-demo',
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'departure_time' => '14:00',
                'arrival_time' => '16:10',
                'duration_minutes' => 130,
                'price' => 1450000,
                'currency' => 'VND',
                'is_demo' => true,
            ],
            [
                'airline' => 'Vietnam Airlines',
                'flight_number' => 'VN-demo',
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'departure_time' => '09:30',
                'arrival_time' => '11:40',
                'duration_minutes' => 130,
                'price' => 1690000,
                'currency' => 'VND',
                'is_demo' => true,
            ],
        ];
    }
}
