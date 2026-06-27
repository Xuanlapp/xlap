<?php

namespace App\Services\Flight;

class FlightRecommendationService
{
    /**
     * Build a concise recommendation message from flight results.
     *
     * @param  array<int, array<string, mixed>>  $flights
     */
    public function summarize(array $flights, string $origin, string $destination, string $departureDate): string
    {
        if ($flights === []) {
            return "Mình chưa tìm thấy chuyến bay nào cho {$origin} → {$destination} ngày {$departureDate}.";
        }

        usort($flights, fn (array $left, array $right): int => (int) $left['price'] <=> (int) $right['price']);

        $cheapest = $flights[0];
        $bestValue = $this->bestValueFlight($flights) ?? $cheapest;
        $lines = [
            "Các chuyến {$origin} → {$destination} ngày {$departureDate}:",
        ];

        foreach ($flights as $index => $flight) {
            $lines[] = sprintf(
                "%d. %s - %s → %s - %s",
                $index + 1,
                (string) $flight['airline'],
                (string) $flight['departure_time'],
                (string) $flight['arrival_time'],
                $this->formatPrice((int) $flight['price'])
            );
        }

        $lines[] = '';
        $lines[] = 'Rẻ nhất: '.$cheapest['airline'].' '.$cheapest['departure_time'].' - '.$this->formatPrice((int) $cheapest['price']);
        $lines[] = 'Nên chọn: '.$bestValue['airline'].' '.$bestValue['departure_time'].' - '.$this->formatPrice((int) $bestValue['price']);

        if (($cheapest['is_demo'] ?? false) === true) {
            $lines[] = '';
            $lines[] = 'Lưu ý: đây đang là dữ liệu demo. Kết nối API vé thật là bot sẽ trả giá realtime.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $flights
     * @return array<string, mixed>|null
     */
    private function bestValueFlight(array $flights): ?array
    {
        $preferred = array_values(array_filter($flights, function (array $flight): bool {
            return (string) $flight['departure_time'] >= '07:00'
                && (string) $flight['departure_time'] <= '20:00';
        }));

        if ($preferred === []) {
            return null;
        }

        usort($preferred, fn (array $left, array $right): int => (int) $left['price'] <=> (int) $right['price']);

        return $preferred[0];
    }

    /**
     * Format a VND amount for chat output.
     */
    private function formatPrice(int $price): string
    {
        return number_format($price, 0, ',', '.').'đ';
    }
}
