<?php

namespace App\Livewire\Modals\Salary;

use App\Livewire\Pages\Salary\Wali;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
use App\Models\DataSalaryZhuzhuPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class CreatePeriod extends Component
{
    public bool $isOpen = false;
    public string $year = '';
    public string $month = '';
    public array $employees = [];
    public array $selectedEmployees = [];
    public string $sourceLabel = '';

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.salary.create-period') {
            return;
        }

        $this->open(
            (string) ($arguments['year'] ?? now()->format('Y')),
            (string) ($arguments['month'] ?? now()->format('m')),
        );
    }

    public function open(string $year, string $month): void
    {
        $this->resetValidation();

        $base = preg_match('/^\d{4}$/', $year) && preg_match('/^\d{1,2}$/', $month)
            ? CarbonImmutable::createFromDate((int) $year, (int) $month, 1)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        if (! $this->periodExists($base)) {
            $target = $base;
        } else {
            $target = $this->firstAvailablePeriodAround($base);
        }

        $this->year = (string) $target->year;
        $this->month = str_pad((string) $target->month, 2, '0', STR_PAD_LEFT);
        $this->loadEmployees();
        $this->isOpen = true;
    }

    public function updatedYear(): void
    {
        $this->normalizeYearForMonth();
        $this->loadEmployees();
    }

    public function updatedMonth(): void
    {
        $this->month = preg_match('/^\d{1,2}$/', $this->month) ? str_pad($this->month, 2, '0', STR_PAD_LEFT) : $this->month;
        $this->normalizeYearForMonth();
        $this->loadEmployees();
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'year', 'month', 'employees', 'selectedEmployees', 'sourceLabel']);
    }

    public function save(): void
    {
        try {
            $validated = $this->validate([
                'year' => ['required', 'digits:4'],
                'month' => ['required', 'regex:/^(0?[1-9]|1[0-2])$/'],
                'selectedEmployees' => ['array'],
            ], [
                'year.required' => 'Vui long chon nam.',
                'month.required' => 'Vui long chon thang.',
                'month.regex' => 'Thang khong hop le.',
            ]);

            $period = CarbonImmutable::createFromDate((int) $validated['year'], (int) $validated['month'], 1)->startOfMonth();

            if ($this->periodExists($period)) {
                throw ValidationException::withMessages([
                    'month' => 'Ky luong nay da ton tai roi.',
                ]);
            }

            DataSalaryZhuzhuPeriod::create([
                'user_id' => auth()->id(),
                'salary_month' => $period->toDateString(),
            ]);

            $selectedIds = collect($this->selectedEmployees)
                ->filter(fn (bool $isSelected): bool => $isSelected)
                ->keys()
                ->map(fn (int|string $id): int => (int) $id)
                ->all();

            if ($selectedIds === []) {
                $this->dispatch('toast', type: 'warning', title: 'Da tao ky trong', message: 'Chua co nhan vien nao de copy sang ky nay.');
                $this->dispatch('wali-salary-updated', salaryMonth: $period->format('Y-m'))->to(Wali::class);
                $this->close();
                $this->dispatch('closeModal');

                return;
            }

            $employeeSnapshots = collect($this->employees)->keyBy('id')->only($selectedIds);

            foreach ($employeeSnapshots as $employee) {
                DataSalaryZhuzhu::updateOrCreate(
                    [
                        'user_id' => auth()->id(),
                        'employee_id' => (int) $employee['id'],
                        'salary_month' => $period->toDateString(),
                    ],
                    [
                        'user_id' => auth()->id(),
                        'employee_id' => (int) $employee['id'],
                        'employee_name' => $employee['name'],
                        'salary_month' => $period->toDateString(),
                        'base_salary' => (float) $employee['base_salary'],
                        'allowed_leave_days' => (int) $employee['allowed_leave_days'],
                    ]
                );
            }

            $this->dispatch('toast', type: 'success', title: 'Da tao ky luong!', message: 'Da tao ky luong '.$period->format('m/Y').'.');
            $this->dispatch('wali-salary-updated', salaryMonth: $period->format('Y-m'))->to(Wali::class);
            $this->close();
            $this->dispatch('closeModal');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            $this->dispatch('toast', type: 'error', title: 'Loi', message: 'Khong the tao ky luong. Vui long thu lai.');
        }
    }

    public function render(): View
    {
        return view('livewire.modals.salary.create-period', [
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    private function loadEmployees(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year) || ! preg_match('/^(0?[1-9]|1[0-2])$/', $this->month)) {
            $this->employees = [];
            $this->selectedEmployees = [];
            $this->sourceLabel = '';

            return;
        }

        $targetPeriod = CarbonImmutable::createFromDate((int) $this->year, (int) $this->month, 1)->startOfMonth();
        $previousPeriod = DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', '<', $targetPeriod->toDateString())
            ->orderByDesc('salary_month')
            ->value('salary_month');

        if ($previousPeriod) {
            $previousMonth = CarbonImmutable::parse($previousPeriod)->startOfMonth();
            $this->employees = DataSalaryZhuzhu::query()
                ->where('user_id', auth()->id())
                ->whereDate('salary_month', $previousMonth->toDateString())
                ->orderBy('employee_name')
                ->get()
                ->map(fn (DataSalaryZhuzhu $row): array => [
                    'id' => (int) $row->employee_id,
                    'name' => $row->employee_name,
                    'base_salary' => (float) $row->base_salary,
                    'allowed_leave_days' => (int) $row->allowed_leave_days,
                    'source' => $previousMonth->format('m/Y'),
                ])
                ->filter(fn (array $row): bool => $row['id'] > 0)
                ->values()
                ->all();
            $this->sourceLabel = 'Lay danh sach tu ky '.$previousMonth->format('m/Y').'. Bo tick nhan vien da nghi viec.';
        } else {
            $this->employees = DataSalaryZhuzhuEmployee::query()
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->orderBy('employee_name')
                ->get()
                ->map(fn (DataSalaryZhuzhuEmployee $employee): array => [
                    'id' => (int) $employee->id,
                    'name' => $employee->employee_name,
                    'base_salary' => (float) $employee->base_salary,
                    'allowed_leave_days' => (int) $employee->allowed_leave_days,
                    'source' => 'active',
                ])
                ->values()
                ->all();
            $this->sourceLabel = 'Chua co ky truoc, lay danh sach nhan vien active.';
        }

        $this->selectedEmployees = collect($this->employees)
            ->mapWithKeys(fn (array $employee): array => [
                (int) $employee['id'] => true,
            ])
            ->all();
    }

    private function monthOptions(): Collection
    {
        $allMonths = collect(range(1, 12))->map(fn (int $month): array => [
            'value' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            'number' => $month,
        ]);

        return $allMonths
            ->filter(function (array $month): bool {
                $candidateYear = $this->candidateYearForMonth($month['number']);

                return ! DataSalaryZhuzhuPeriod::query()
                    ->where('user_id', auth()->id())
                    ->whereDate('salary_month', CarbonImmutable::createFromDate($candidateYear, $month['number'], 1)->toDateString())
                    ->exists();
            })
            ->values();
    }

    private function yearOptions(): array
    {
        $currentYear = (int) now()->format('Y');
        $month = (int) ($this->month ?: now()->format('m'));

        if ($month === 12) {
            return [$currentYear, $currentYear + 1];
        }

        if ($month === 1) {
            return [$currentYear - 1, $currentYear];
        }

        return [$currentYear];
    }

    private function normalizeYearForMonth(): void
    {
        $yearOptions = $this->yearOptions();

        if (! in_array((int) $this->year, $yearOptions, true)) {
            $this->year = (string) $yearOptions[0];
        }
    }

    private function candidateYearForMonth(int $month): int
    {
        $currentYear = (int) now()->format('Y');
        $selectedYear = (int) ($this->year ?: $currentYear);

        if ($month === 12) {
            return in_array($selectedYear, [$currentYear, $currentYear + 1], true) ? $selectedYear : $currentYear;
        }

        if ($month === 1) {
            return in_array($selectedYear, [$currentYear - 1, $currentYear], true) ? $selectedYear : $currentYear;
        }

        return $currentYear;
    }

    private function firstAvailablePeriodAround(CarbonImmutable $base): CarbonImmutable
    {
        if (! $this->periodExists($base)) {
            return $base;
        }

        $next = $base->addMonth();
        if (! $this->periodExists($next)) {
            return $next;
        }

        $previous = $base->subMonth();
        if (! $this->periodExists($previous)) {
            return $previous;
        }

        $target = CarbonImmutable::now()->startOfMonth();
        while ($this->periodExists($target)) {
            $target = $target->addMonth();
        }

        return $target;
    }

    private function periodExists(CarbonImmutable $period): bool
    {
        return DataSalaryZhuzhuPeriod::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $period->toDateString())
            ->exists();
    }
}