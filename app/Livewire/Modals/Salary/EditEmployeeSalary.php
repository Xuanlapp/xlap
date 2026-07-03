<?php

namespace App\Livewire\Modals\Salary;

use App\Livewire\Pages\Salary\Wali;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
use App\Services\Salary\WaliSalaryCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class EditEmployeeSalary extends Component
{
    public bool $isOpen = false;

    public string $salaryMonth = '';

    public ?int $employeeId = null;

    public array $row = [];

    public bool $confirmingDelete = false;

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.salary.edit-employee-salary') {
            return;
        }

        $this->open(
            (int) ($arguments['employeeId'] ?? 0),
            (string) ($arguments['salaryMonth'] ?? now()->format('Y-m')),
        );
    }

    public function open(int $employeeId, string $salaryMonth): void
    {
        $this->resetValidation();
        $this->employeeId = $employeeId;
        $this->salaryMonth = preg_match('/^\d{4}-\d{2}$/', $salaryMonth) ? $salaryMonth : now()->format('Y-m');

        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();
        $employee = DataSalaryZhuzhuEmployee::query()
            ->where('user_id', auth()->id())
            ->where('id', $employeeId)
            ->first();

        $saved = DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $month->toDateString())
            ->where(function ($query) use ($employeeId, $employee) {
                $query->where('employee_id', $employeeId);

                if ($employee) {
                    $query->orWhere('employee_name', $employee->employee_name);
                }
            })
            ->first();

        $employeeName = (string) ($saved?->employee_name ?? $employee?->employee_name ?? 'Nhan vien');
        $baseSalary = $saved?->base_salary ?? $employee?->base_salary ?? 0;

        $payload = [
            'base_salary' => $this->moneyInput($baseSalary),
            'performance_score' => $this->scoreInput($saved?->performance_score ?? 0),
            'late_minutes' => (int) ($saved?->late_minutes ?? 0),
            'leave_days' => $this->numberInput($saved?->leave_days ?? 0),
            'allowed_leave_days' => (int) ($saved?->allowed_leave_days ?? $employee?->allowed_leave_days ?? 0),
            'daily_bonus' => $this->moneyInput($saved?->daily_bonus ?? 0),
            'supplement' => $this->moneyInput($saved?->supplement ?? 0),
            'other_money' => $this->moneyInput($saved?->other_money ?? 0),
            'note' => (string) ($saved?->note ?? ''),
        ];

        $computed = app(WaliSalaryCalculator::class)->calculate($payload, $month);

        $this->row = [
            'employee_id' => $employeeId,
            'employee_name' => $employeeName,
            ...$payload,
            'standard_work_days' => $computed['standard_work_days'],
            'actual_work_days' => $computed['actual_work_days'],
            'late_penalty_score' => $computed['late_penalty_score'],
            'payroll_score' => $computed['payroll_score'],
            'variable_salary' => $computed['variable_salary'],
            'commission' => $computed['commission'],
            'odd_point_money' => $computed['odd_point_money'],
            'total_salary' => $computed['total_salary'],
            'net_received' => $computed['net_received'],
        ];

        $this->isOpen = true;
    }

    public function updatedRow(): void
    {
        $this->recalculate();
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'salaryMonth', 'employeeId', 'row', 'confirmingDelete']);
    }


    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteSalaryRow(): void
    {
        if (! $this->employeeId || $this->salaryMonth === '') {
            return;
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();

        DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->where('employee_id', $this->employeeId)
            ->whereDate('salary_month', $month->toDateString())
            ->delete();

        $this->dispatch('toast', type: 'success', title: 'Da xoa!', message: 'Da xoa nhan vien khoi ky luong nay.');
        $this->confirmingDelete = false;
        $this->close();
        $this->dispatch('wali-salary-updated', salaryMonth: $month->format('Y-m'))->to(Wali::class);
        $this->redirect(request()->header('Referer') ?: route('offorest.salary.wali'), navigate: false);
    }

    public function save(): void
    {
        if (! $this->employeeId || $this->salaryMonth === '') {
            return;
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();
        $computed = app(WaliSalaryCalculator::class)->calculate($this->row, $month);
        $employee = DataSalaryZhuzhuEmployee::query()
            ->where('user_id', auth()->id())
            ->where('id', $this->employeeId)
            ->first();

        DataSalaryZhuzhu::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'employee_id' => $this->employeeId,
                'salary_month' => $month->toDateString(),
            ],
            [
                'user_id' => auth()->id(),
                'employee_id' => $this->employeeId,
                'employee_name' => (string) ($this->row['employee_name'] ?? $employee?->employee_name ?? ''),
                'salary_month' => $month->toDateString(),
                'base_salary' => $this->parseMoney($this->row['base_salary'] ?? 0),
                'performance_score' => $this->parseMoney($this->row['performance_score'] ?? 0),
                'late_minutes' => (int) ($this->row['late_minutes'] ?? 0),
                'late_days' => $computed['late_penalty_score'],
                'leave_days' => $this->parseNumber($this->row['leave_days'] ?? 0),
                'allowed_leave_days' => (int) ($this->row['allowed_leave_days'] ?? 0),
                'standard_work_days' => $computed['standard_work_days'],
                'actual_work_days' => $computed['actual_work_days'],
                'score' => $computed['payroll_score'],
                'variable_salary' => $computed['variable_salary'],
                'daily_bonus' => $this->parseMoney($this->row['daily_bonus'] ?? 0),
                'supplement' => $this->parseMoney($this->row['supplement'] ?? 0),
                'other_money' => $this->parseMoney($this->row['other_money'] ?? 0),
                'note' => (string) ($this->row['note'] ?? ''),
                'total_salary' => $computed['total_salary'],
                'odd_point_money' => $computed['odd_point_money'],
                'commission' => $computed['commission'],
                'net_received' => $computed['net_received'],
            ]
        );

        if ($employee) {
            $employee->forceFill([
                'base_salary' => $this->parseMoney($this->row['base_salary'] ?? 0),
                'allowed_leave_days' => (int) ($this->row['allowed_leave_days'] ?? 0),
            ])->save();
        }

        $this->dispatch('toast', type: 'success', title: 'Da luu!', message: 'Da cap nhat luong nhan vien.');
        $this->dispatch('wali-salary-updated')->to(Wali::class);
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.salary.edit-employee-salary', [
            'monthLabel' => $this->salaryMonth !== ''
                ? CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->format('m/Y')
                : '',
        ]);
    }

    private function recalculate(): void
    {
        if ($this->salaryMonth === '' || $this->row === []) {
            return;
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();
        $computed = app(WaliSalaryCalculator::class)->calculate($this->row, $month);

        $this->row['standard_work_days'] = $computed['standard_work_days'];
        $this->row['actual_work_days'] = $computed['actual_work_days'];
        $this->row['late_penalty_score'] = $computed['late_penalty_score'];
        $this->row['payroll_score'] = $computed['payroll_score'];
        $this->row['variable_salary'] = $computed['variable_salary'];
        $this->row['commission'] = $computed['commission'];
        $this->row['odd_point_money'] = $computed['odd_point_money'];
        $this->row['total_salary'] = $computed['total_salary'];
        $this->row['net_received'] = $computed['net_received'];
    }

    private function parseMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);
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

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function parseNumber(mixed $value): float
    {
        return $this->parseMoney($value);
    }

    private function moneyInput(mixed $value): string
    {
        $amount = $this->parseMoney($value);

        return $amount > 0 ? number_format($amount, 0, ',', '.') : '';
    }

    private function scoreInput(mixed $value): string
    {
        $amount = $this->parseMoney($value);

        if ($amount <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($amount, 2, ',', '.'), '0'), ',');
    }

    private function numberInput(mixed $value): string
    {
        $amount = $this->parseNumber($value);

        if ($amount <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($amount, 2, ',', '.'), '0'), ',');
    }
}
