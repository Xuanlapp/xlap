<?php

namespace App\Services\Salary;

use Carbon\CarbonImmutable;

class WaliSalaryCalculator
{
    /**
     * @return array{standard_work_days:int, actual_work_days:float, late_penalty_score:float, payroll_score:float, variable_salary:float, commission:float, odd_point_money:float, total_salary:float, net_received:float}
     */
    public function calculate(array $payload, CarbonImmutable $month): array
    {
        $baseSalary = $this->parseNumber($payload['base_salary'] ?? 0);
        $performanceScore = $this->parseNumber($payload['performance_score'] ?? 0);
        $lateMinutes = (int) ($payload['late_minutes'] ?? 0);
        $leaveDays = $this->parseNumber($payload['leave_days'] ?? 0);
        $allowedLeaveDays = $this->parseNumber($payload['allowed_leave_days'] ?? 0);
        $dailyBonus = $this->parseNumber($payload['daily_bonus'] ?? 0);
        $supplement = $this->parseNumber($payload['supplement'] ?? 0);
        $otherMoney = $this->parseNumber($payload['other_money'] ?? 0);

        $latePenaltyScore = $this->lateMinutesToPenaltyPoints($lateMinutes);
        $payrollScore = round(max(0, $performanceScore - $latePenaltyScore), 1);
        $standardWorkDays = $this->standardWorkDaysForMonth($month, $allowedLeaveDays);
        $variableSalary = $this->variableSalaryByScore($payrollScore);
        $commission = $this->commissionByScore($payrollScore);
        $oddPointMoney = $this->oddPointMoney($payrollScore);
        $overLeaveDays = max(0, $leaveDays - $allowedLeaveDays);
        $actualWorkDays = max(0, $standardWorkDays - $leaveDays);
        $totalSalary = round($baseSalary + $variableSalary);
        $netReceived = round($totalSalary + $oddPointMoney + $commission + $dailyBonus + $supplement + $otherMoney);

        return [
            'standard_work_days' => $standardWorkDays,
            'actual_work_days' => $actualWorkDays,
            'over_leave_days' => $overLeaveDays,
            'late_penalty_score' => $latePenaltyScore,
            'payroll_score' => $payrollScore,
            'variable_salary' => $variableSalary,
            'commission' => $commission,
            'odd_point_money' => $oddPointMoney,
            'total_salary' => $totalSalary,
            'net_received' => $netReceived,
        ];
    }

    public function standardWorkDaysForMonth(CarbonImmutable $month, float $allowedLeaveDays = 0): int
    {
        return max(0, (int) round($month->daysInMonth - $allowedLeaveDays));
    }

    public function lateMinutesToPenaltyPoints(int $lateMinutes): float
    {
        if ($lateMinutes <= 0) {
            return 0;
        }

        return floor($lateMinutes / 10) * 5;
    }

    public function variableSalaryByScore(float $score): float
    {
        $bands = [
            600 => 360000,
            900 => 730000,
            1200 => 1100000,
            1500 => 1480000,
            2000 => 1850000,
            2500 => 2220000,
            3000 => 2590000,
            3500 => 2960000,
            4000 => 3330000,
            4500 => 3700000,
        ];

        if ($score < 600) {
            return 0;
        }

        $result = 0;
        foreach ($bands as $threshold => $value) {
            if ($score >= $threshold) {
                $result = $value;
            }
        }

        return $result;
    }

    public function commissionByScore(float $score): float
    {
        $bands = [
            600 => 0,
            900 => 0,
            1200 => 740000,
            1500 => 1850000,
            2000 => 5550000,
            2500 => 9250000,
            3000 => 12950000,
            3500 => 16650000,
            4000 => 20350000,
            4500 => 24050000,
        ];

        if ($score < 600) {
            return 0;
        }

        $result = 0;
        foreach ($bands as $threshold => $value) {
            if ($score >= $threshold) {
                $result = $value;
            }
        }

        return $result;
    }

    public function oddPointMoney(float $score): float
    {
        $baseThreshold = $this->baseThresholdForScore($score);

        if ($baseThreshold === 0) {
            return 0;
        }

        $remainder = $score - $baseThreshold;

        if ($remainder <= 0) {
            return 0;
        }

        return round($remainder * 3700);
    }

    private function baseThresholdForScore(float $score): int
    {
        $thresholds = [600, 900, 1200, 1500, 2000, 2500, 3000, 3500, 4000, 4500];
        $baseThreshold = 0;

        foreach ($thresholds as $threshold) {
            if ($score >= $threshold) {
                $baseThreshold = $threshold;
            }
        }

        return $baseThreshold;
    }

    private function parseNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return 0;
        }

        $normalized = str_replace(' ', '', $normalized);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0;
    }
}
