<?php

namespace App\Livewire\Modals\Salary;

use App\Livewire\Pages\Salary\Wali;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class AddEmployee extends Component
{
    public bool $isOpen = false;
    public string $employeeName = '';
    public string $baseSalary = '';
    public string $salaryMonth = '';

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.salary.add-employee') {
            return;
        }

        $this->open((string) ($arguments['salaryMonth'] ?? now()->format('Y-m')));
    }

    public function open(string $salaryMonth): void
    {
        $this->resetValidation();
        $this->salaryMonth = preg_match('/^\d{4}-\d{2}$/', $salaryMonth) ? $salaryMonth : now()->format('Y-m');
        $this->employeeName = '';
        $this->baseSalary = '';
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'employeeName', 'baseSalary', 'salaryMonth']);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'employeeName' => ['required', 'string', 'max:255'],
            'baseSalary' => ['required', 'numeric', 'min:0'],
        ]);

        $employee = DataSalaryZhuzhuEmployee::updateOrCreate(
            ['user_id' => auth()->id(), 'employee_name' => trim($validated['employeeName'])],
            ['base_salary' => (float) $validated['baseSalary'], 'is_active' => true]
        );

        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();

        DataSalaryZhuzhu::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'employee_id' => $employee->id,
                'salary_month' => $month->toDateString(),
            ],
            [
                'user_id' => auth()->id(),
                'employee_id' => $employee->id,
                'employee_name' => $employee->employee_name,
                'salary_month' => $month->toDateString(),
                'base_salary' => (float) $employee->base_salary,
            ]
        );

        $this->dispatch('toast', type: 'success', title: 'Da them!', message: 'Da tao nhan vien moi thanh cong.');
        $this->dispatch('wali-salary-updated')->to(Wali::class);
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.salary.add-employee');
    }
}
