<?php

namespace App\Services\Flight;

use Carbon\Carbon;
use InvalidArgumentException;

class FlightIntentParser
{
    /**
     * Parse a Telegram message into flight search intent data.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function parse(string $message, array $context = []): array
    {
        $normalizedMessage = mb_strtolower(trim($message));
        $origin = $this->extractAirport($normalizedMessage, ['sgn', 'sài gòn', 'sai gon', 'hồ chí minh', 'ho chi minh'], 'SGN')
            ?? (string) ($context['origin'] ?? 'SGN');
        $destination = $this->extractAirport($normalizedMessage, ['han', 'hà nội', 'ha noi'], 'HAN')
            ?? (string) ($context['destination'] ?? 'HAN');
        $departureDate = $this->extractDate($normalizedMessage) ?? ($context['departure_date'] ?? null);
        $tripType = str_contains($normalizedMessage, 'khứ hồi') ? 'round_trip' : (string) ($context['trip_type'] ?? 'one_way');
        $isConfirmation = in_array($normalizedMessage, ['đúng', 'dung', 'ok', 'đúng rồi', 'yes'], true);

        return [
            'origin' => $origin,
            'destination' => $destination,
            'departure_date' => $departureDate,
            'trip_type' => $tripType,
            'is_confirmation' => $isConfirmation,
            'needs_departure_date' => $departureDate === null,
        ];
    }

    /**
     * Extract an airport code from known city keywords.
     *
     * @param  array<int, string>  $keywords
     */
    private function extractAirport(string $message, array $keywords, string $airportCode): ?string
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return $airportCode;
            }
        }

        return null;
    }

    /**
     * Extract a travel date in Y-m-d format.
     */
    private function extractDate(string $message): ?string
    {
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/u', $message, $matches) === 1) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = isset($matches[3]) ? (int) $matches[3] : (int) now()->year;

            if ($year < 100) {
                $year += 2000;
            }

            return $this->formatDate($year, $month, $day);
        }

        if (str_contains($message, 'ngày mai') || str_contains($message, 'mai')) {
            return now()->addDay()->toDateString();
        }

        if (str_contains($message, 'hôm nay')) {
            return now()->toDateString();
        }

        return null;
    }

    /**
     * Validate and format a date.
     */
    private function formatDate(int $year, int $month, int $day): string
    {
        try {
            return Carbon::createFromDate($year, $month, $day)->toDateString();
        } catch (InvalidArgumentException) {
            return now()->toDateString();
        }
    }
}
