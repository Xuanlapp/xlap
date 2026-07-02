<?php

namespace App\Livewire\Modals\Salary;

use App\Livewire\Pages\Salary\Wali;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
use App\Models\DataSalaryZhuzhuPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
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
        $this->year = preg_match('/^\d{4}$/', $year) ? $year : now()->format('Y');
        $this->month = preg_match('/^\d{1,2}$/', $month) ? str_pad($month, 2, '0', STR_PAD_LEFT) : now()->format('m');
        $this->loadEmployees();
        $this->isOpen = true;
    }

    public function updatedYear(): void
    {
        $this->loadEmployees();
    }

    public function updatedMonth(): void
    {
        $this->month = preg_match('/^\d{1,2}$/', $this->month) ? str_pad($this->month, 2, '0', STR_PAD_LEFT) : $this->month;
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
            $selectedIds = collect($this->selectedEmployees)
                ->filter(fn (bool $isSelected): bool => $isSelected)
                ->keys()
                ->map(fn (int|string $id): int => (int) $id)
                ->all();

            DataSalaryZhuzhuPeriod::updateOrCreate([
                'user_id' => auth()->id(),
                'salary_month' => $period->toDateString(),
            ]);

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
        return view('livewire.modals.salary.create-period');
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

        $existingIds = DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $targetPeriod->toDateString())
            ->pluck('employee_id')
            ->map(fn (int|string|null $id): int => (int) $id)
            ->all();

        $this->selectedEmployees = collect($this->employees)
            ->mapWithKeys(fn (array $employee): array => [
                (int) $employee['id'] => $existingIds === [] || in_array((int) $employee['id'], $existingIds, true),
            ])
            ->all();
    }
}