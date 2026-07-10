<?php

namespace App\Livewire\Pages\Salary;

use App\Livewire\Modals\Salary\AddEmployee;
use App\Livewire\Modals\Salary\CreatePeriod;
use App\Livewire\Modals\Salary\EditEmployeeSalary;
use App\Livewire\Modals\Salary\MonthSummary;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
use App\Models\DataSalaryZhuzhuPeriod;
use App\Services\Salary\WaliSalaryCalculator;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class Wali extends Component
{
    public int $selectedYear = 0;

    public int $selectedMonth = 0;

    public string $employeeSearch = '';

    public array $selectedEmployeeIds = [];

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

    public function updatedEmployeeSearch(string $value): void
    {
        $this->employeeSearch = trim($value);
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

    public function moveEmployee(int $employeeId, string $direction): void
    {
        $month = CarbonImmutable::create($this->selectedYear, max($this->selectedMonth, 1), 1)->startOfMonth();

        $rows = DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $month->toDateString())
            ->orderBy('sort_order')
            ->orderBy('employee_name')
            ->orderBy('id')
            ->get();

        $index = $rows->search(fn (DataSalaryZhuzhu $row) => (int) ($row->employee_id ?? $row->id) === $employeeId);

        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($rows[$targetIndex])) {
            return;
        }

        $current = $rows[$index];
        $target = $rows[$targetIndex];

        DB::transaction(function () use ($current, $target): void {
            $currentSort = (int) ($current->sort_order ?? 0);
            $targetSort = (int) ($target->sort_order ?? 0);

            $current->forceFill(['sort_order' => $targetSort])->save();
            $target->forceFill(['sort_order' => $currentSort])->save();
        });

        $this->dispatch('wali-salary-updated', salaryMonth: $this->selectedSalaryMonth())->to(self::class);
    }

    public function reorderEmployee(int $draggedEmployeeId, int $targetEmployeeId): void
    {
        if ($draggedEmployeeId === $targetEmployeeId) {
            return;
        }

        $month = CarbonImmutable::create($this->selectedYear, max($this->selectedMonth, 1), 1)->startOfMonth();

        $rows = DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $month->toDateString())
            ->orderBy('sort_order')
            ->orderBy('employee_name')
            ->orderBy('id')
            ->get();

        $draggedIndex = $rows->search(fn (DataSalaryZhuzhu $row) => (int) ($row->employee_id ?? $row->id) === $draggedEmployeeId);
        $targetIndex = $rows->search(fn (DataSalaryZhuzhu $row) => (int) ($row->employee_id ?? $row->id) === $targetEmployeeId);

        if ($draggedIndex === false || $targetIndex === false) {
            return;
        }

        $draggedRow = $rows->pull($draggedIndex);
        $rows = $rows->values();
        $insertIndex = $rows->search(fn (DataSalaryZhuzhu $row) => (int) ($row->employee_id ?? $row->id) === $targetEmployeeId);

        if ($insertIndex === false) {
            return;
        }

        $rows->splice($insertIndex, 0, [$draggedRow]);

        DB::transaction(function () use ($rows): void {
            foreach ($rows->values() as $index => $row) {
                $row->forceFill(['sort_order' => $index + 1])->save();
            }
        });

        $this->dispatch('wali-salary-updated', salaryMonth: $this->selectedSalaryMonth())->to(self::class);
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
        $employeeOptions = $this->employeeOptionsForMonth($month);
        $availableMonths = $this->availableMonths();
        $yearOptions = $availableMonths->pluck('year')->unique()->sortDesc()->values();
        $monthOptions = $this->monthOptions($this->selectedYear);
        return view('livewire.pages.salary.wali', [
            'monthLabel' => $month->translatedFormat('m/Y'),
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'yearOptions' => $yearOptions,
            'monthOptions' => $monthOptions,
            'employeeOptions' => $employeeOptions,
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

    private function employeeOptionsForMonth(CarbonImmutable $month): Collection
    {
        return DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $month->toDateString())
            ->orderBy('sort_order')
            ->orderBy('employee_name')
            ->get(['id', 'employee_id', 'employee_name'])
            ->map(fn (DataSalaryZhuzhu $row) => [
                'id' => (string) ($row->employee_id ?? $row->id),
                'name' => (string) $row->employee_name,
            ])
            ->unique('id')
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
            ->when($this->employeeSearch !== '', fn ($query) => $query->where('employee_name', 'like', '%'.$this->employeeSearch.'%'))
            ->orderBy('sort_order')
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

        $filteredRows = $salaryRows->values();

        if ($this->employeeSearch !== '') {
            $needle = mb_strtolower($this->employeeSearch);
            $filteredRows = $filteredRows->filter(fn (DataSalaryZhuzhu $row) => str_contains(mb_strtolower((string) $row->employee_name), $needle));
        }

        $selectedIds = collect($this->selectedEmployeeIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        if ($selectedIds !== []) {
            $filteredRows = $filteredRows->filter(fn (DataSalaryZhuzhu $row) => in_array((string) ($row->employee_id ?? $row->id), $selectedIds, true));
        }

        return $filteredRows
            ->sortBy(fn (DataSalaryZhuzhu $row) => [(int) ($row->sort_order ?? 0), mb_strtolower((string) $row->employee_name)])
            ->map(fn (DataSalaryZhuzhu $row) => $this->decorateRow($row, $month));
    }

    private function decorateRow(DataSalaryZhuzhu $row, CarbonImmutable $month): DataSalaryZhuzhu
    {
        $computed = app(WaliSalaryCalculator::class)->calculate([
            'base_salary' => $row->base_salary,
            'performance_score' => $row->performance_score,
            'late_minutes' => $row->late_minutes,
            'leave_days' => $row->leave_days,
            'allowed_leave_days' => $row->allowed_leave_days,
            'daily_bonus' => $row->daily_bonus,
            'supplement' => $row->supplement,
            'other_money' => $row->other_money,
        ], $month);

        $row->standard_work_days = $computed['standard_work_days'];
        $row->actual_work_days = $computed['actual_work_days'];
        $row->late_days = $computed['late_penalty_score'];
        $row->score = $computed['payroll_score'];
        $row->variable_salary = $computed['variable_salary'];
        $row->commission = $computed['commission'];
        $row->odd_point_money = $computed['odd_point_money'];
        $row->total_salary = $computed['total_salary'];
        $row->net_received = $computed['net_received'];

        return $row;
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
