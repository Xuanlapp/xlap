<?php

namespace App\Livewire\Pages\Salary;

use App\Livewire\Modals\Salary\AddEmployee;
use App\Livewire\Modals\Salary\CreatePeriod;
use App\Livewire\Modals\Salary\EditEmployeeSalary;
use App\Livewire\Modals\Salary\MonthSummary;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
use App\Models\DataSalaryZhuzhuPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class Wali extends Component
{
    public int $selectedYear = 0;

    public int $selectedMonth = 0;

    public function mount(): void
    {
        $latestMonth = $this->availableMonths()->first();

        if ($latestMonth) {
            $this->selectedYear = (int) $latestMonth['year'];
            $this->selectedMonth = (int) $latestMonth['month'];

            return;
        }

        $this->selectedYear = (int) now()->format('Y');
        $this->selectedMonth = (int) now()->format('m');
    }

    public function updatedSelectedYear(int $value): void
    {
        $this->selectedYear = $value;
        $availableMonths = $this->monthOptions($this->selectedYear);

        if ($availableMonths->contains(fn (array $month) => (int) $month['value'] === $this->selectedMonth)) {
            return;
        }

        $this->selectedMonth = (int) ($availableMonths->first()['value'] ?? 0);
    }

    public function updatedSelectedMonth(int $value): void
    {
        $this->selectedMonth = $value;
    }

    public function openCreatePeriod(): void
    {
        $base = CarbonImmutable::create($this->selectedYear ?: (int) now()->format('Y'), max($this->selectedMonth, 1), 1)->startOfMonth();

        $this->dispatch('openModal', component: 'modals.salary.create-period', arguments: [
            'year' => (string) $base->year,
            'month' => str_pad((string) $base->month, 2, '0', STR_PAD_LEFT),
        ])->to(CreatePeriod::class);
    }

    public function openMonthSummary(): void
    {
        $this->dispatch('openModal', component: 'modals.salary.month-summary', arguments: [
            'salaryMonth' => $this->selectedSalaryMonth(),
        ])->to(MonthSummary::class);
    }

    public function openAddEmployee(): void
    {
        $this->dispatch('openModal', component: 'modals.salary.add-employee', arguments: [
            'salaryMonth' => $this->selectedSalaryMonth(),
        ])->to(AddEmployee::class);
    }


    public function exportUrl(): string
    {
        return route('offorest.salary.wali.export', [
            'year' => $this->selectedYear,
            'month' => $this->selectedMonth,
        ]);
    }

    public function openEmployeeSalary(int $employeeId): void
    {
        $this->dispatch('openModal', component: 'modals.salary.edit-employee-salary', arguments: [
            'employeeId' => $employeeId,
            'salaryMonth' => $this->selectedSalaryMonth(),
        ])->to(EditEmployeeSalary::class);
    }

    #[On('wali-salary-updated')]
    public function salaryUpdated(?string $salaryMonth = null): void
    {
        if (! $salaryMonth) {
            return;
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $salaryMonth)->startOfMonth();

        $this->selectedYear = (int) $month->year;
        $this->selectedMonth = (int) $month->month;
    }

    public function render(): View
    {
        $month = CarbonImmutable::create($this->selectedYear, max($this->selectedMonth, 1), 1)->startOfMonth();
        $rows = $this->rowsForMonth($month);
        $availableMonths = $this->availableMonths();
        $yearOptions = $availableMonths->pluck('year')->unique()->sortDesc()->values();
        $monthOptions = $this->monthOptions($this->selectedYear);

        return view('livewire.pages.salary.wali', [
            'monthLabel' => $month->translatedFormat('m/Y'),
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'yearOptions' => $yearOptions,
            'monthOptions' => $monthOptions,
        ])->layout('layouts.app');
    }

    private function availableMonths(): Collection
    {
        return DataSalaryZhuzhuPeriod::query()
            ->where('user_id', auth()->id())
            ->selectRaw('YEAR(salary_month) as year, MONTH(salary_month) as month')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn (object $row) => [
                'year' => (int) $row->year,
                'month' => (int) $row->month,
            ]);
    }

    private function selectedSalaryMonth(): string
    {
        return sprintf('%04d-%02d', $this->selectedYear, max($this->selectedMonth, 1));
    }

    private function monthOptions(int $year): Collection
    {
        return $this->availableMonths()
            ->where('year', $year)
            ->map(fn (array $row) => [
                'value' => (int) $row['month'],
                'label' => str_pad((string) $row['month'], 2, '0', STR_PAD_LEFT),
            ])
            ->values();
    }

    private function rowsForMonth(CarbonImmutable $month): Collection
    {
        return $this->exportRowsForMonth($month);
    }

    public function exportRowsForMonth(CarbonImmutable $month): Collection
    {
        $salaryRows = DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $month->toDateString())
            ->orderBy('employee_name')
            ->get()
            ->keyBy(fn (DataSalaryZhuzhu $row) => (string) ($row->employee_id ?? $row->id));

        $employees = DataSalaryZhuzhuEmployee::query()
            ->where('user_id', auth()->id())
            ->orderBy('employee_name')
            ->get()
            ->keyBy(fn (DataSalaryZhuzhuEmployee $employee) => (string) $employee->id);

        foreach ($salaryRows as $row) {
            $employee = $employees->get((string) $row->employee_id);

            if ($employee) {
                $row->setAttribute('avatar_path', $employee->avatar_path);
            }
        }

        return $salaryRows
            ->values()
            ->sortBy(fn (DataSalaryZhuzhu $row) => mb_strtolower((string) $row->employee_name))
            ->map(fn (DataSalaryZhuzhu $row) => $this->decorateRow($row, $month));
    }

    private function decorateRow(DataSalaryZhuzhu $row, CarbonImmutable $month): DataSalaryZhuzhu
    {
        $standardWorkDays = $this->standardWorkDaysForMonth($month);
        $row->standard_work_days = $standardWorkDays;

        $latePenaltyScore = $this->lateMinutesToPenaltyPoints((int) $row->late_minutes);
        $payrollScore = max(0, (float) $row->performance_score - $latePenaltyScore);

        $row->late_days = $latePenaltyScore;
        $row->score = $payrollScore;
        $row->actual_work_days = max(0, $standardWorkDays - (float) $row->leave_days);
        $row->variable_salary = $this->variableSalaryByScore($payrollScore);
        $row->commission = $this->commissionByScore($payrollScore);
        $row->odd_point_money = $this->oddPointMoney($payrollScore);
        $row->total_salary = round((float) $row->base_salary + (float) $row->variable_salary);
        $row->net_received = round((float) $row->total_salary + (float) $row->odd_point_money + (float) $row->commission + (float) $row->daily_bonus + (float) $row->other_money + (float) $row->supplement);

        return $row;
    }

    private function standardWorkDaysForMonth(CarbonImmutable $month): int
    {
        $daysInMonth = $month->daysInMonth;
        $sundays = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            if ($month->day($day)->isSunday()) {
                $sundays++;
            }
        }

        return $daysInMonth - $sundays;
    }

    private function lateMinutesToPenaltyPoints(int $lateMinutes): float
    {
        if ($lateMinutes <= 0) {
            return 0;
        }

        return floor($lateMinutes / 10) * 5;
    }

    private function variableSalaryByScore(float $score): float
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

        $baseScore = 0;
        $baseValue = 0;

        foreach ($bands as $threshold => $value) {
            if ($score >= $threshold) {
                $baseScore = $threshold;
                $baseValue = $value;
            }
        }

        return $baseValue;
    }

    private function commissionByScore(float $score): float
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

        $commission = 0;

        foreach ($bands as $threshold => $value) {
            if ($score >= $threshold) {
                $commission = $value;
            }
        }

        return (float) $commission;
    }

    private function oddPointMoney(float $score): float
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

    private function summary(Collection $rows): array
    {
        return [
            'employees' => $rows->count(),
            'total_salary' => $rows->sum(fn (DataSalaryZhuzhu $row) => (float) $row->total_salary),
            'net_received' => $rows->sum(fn (DataSalaryZhuzhu $row) => (float) $row->net_received),
        ];
    }
}
