<?php

namespace App\Livewire\Modals\Salary;

use App\Livewire\Pages\Salary\Wali;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
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
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'year', 'month']);
    }

    public function save(): void
    {
        try {
            $validated = $this->validate([
                'year' => ['required', 'digits:4'],
                'month' => ['required', 'integer', 'between:1,12'],
            ], [
                'year.required' => 'Vui long chon nam.',
                'month.required' => 'Vui long chon thang.',
            ]);

            $period = CarbonImmutable::createFromDate((int) $validated['year'], (int) $validated['month'], 1)->startOfMonth();

            $existingEmployees = DataSalaryZhuzhuEmployee::query()
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->orderBy('employee_name')
                ->get();

            foreach ($existingEmployees as $employee) {
                DataSalaryZhuzhu::updateOrCreate(
                    [
                        'user_id' => auth()->id(),
                        'employee_id' => $employee->id,
                        'salary_month' => $period->toDateString(),
                    ],
                    [
                        'user_id' => auth()->id(),
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->employee_name,
                        'salary_month' => $period->toDateString(),
                        'base_salary' => (float) $employee->base_salary,
                        'allowed_leave_days' => (int) $employee->allowed_leave_days,
                    ]
                );
            }

            $this->dispatch('toast', type: 'success', title: 'Da tao ky luong!', message: 'Da tao ky luong moi thanh cong.');
            $this->dispatch('wali-salary-updated')->to(Wali::class);
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
}
