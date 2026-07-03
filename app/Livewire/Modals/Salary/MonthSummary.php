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

class MonthSummary extends Component
{
    public bool $isOpen = false;

    public string $salaryMonth = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.salary.month-summary') {
            return;
        }

        $this->open((string) ($arguments['salaryMonth'] ?? now()->format('Y-m')));
    }

    public function open(string $salaryMonth): void
    {
        $this->resetValidation();
        $this->salaryMonth = preg_match('/^\d{4}-\d{2}$/', $salaryMonth) ? $salaryMonth : now()->format('Y-m');
        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();

        $salaryRows = DataSalaryZhuzhu::query()
            ->where('user_id', auth()->id())
            ->whereDate('salary_month', $month->toDateString())
            ->get()
            ->keyBy(fn (DataSalaryZhuzhu $row) => (string) ($row->employee_id ?? $row->id));

        $employees = DataSalaryZhuzhuEmployee::query()
            ->where('user_id', auth()->id())
            ->orderBy('employee_name')
            ->get()
            ->keyBy(fn (DataSalaryZhuzhuEmployee $employee) => (string) $employee->id);

        $calculator = app(WaliSalaryCalculator::class);
        $this->rows = [];

        foreach ($salaryRows->values()->sortBy(fn (DataSalaryZhuzhu $saved) => mb_strtolower((string) $saved->employee_name)) as $saved) {
            $employee = $employees->get((string) $saved->employee_id);
            $payload = [
                'base_salary' => $this->parseMoney($saved->base_salary ?? 0),
                'performance_score' => $this->parseMoney($saved->performance_score ?? 0),
                'late_minutes' => (int) ($saved->late_minutes ?? 0),
                'leave_days' => $this->parseNumber($saved->leave_days ?? 0),
                'allowed_leave_days' => (int) ($saved->allowed_leave_days ?? $employee?->allowed_leave_days ?? 0),
                'daily_bonus' => $this->moneyInput($saved->daily_bonus ?? 0),
                'supplement' => $this->moneyInput($saved->supplement ?? 0),
                'other_money' => $this->moneyInput($saved->other_money ?? 0),
            ];
            $computed = $calculator->calculate($payload, $month);

            $this->rows[] = [
                'employee_id' => $saved->employee_id ?? $saved->id,
                'employee_name' => $saved->employee_name,
                'base_salary' => $payload['base_salary'],
                'performance_score' => $payload['performance_score'],
                'late_minutes' => $payload['late_minutes'],
                'leave_days' => $payload['leave_days'],
                'allowed_leave_days' => $payload['allowed_leave_days'],
                'daily_bonus' => $payload['daily_bonus'],
                'other_money' => $payload['other_money'],
                'supplement' => $payload['supplement'],
                'note' => (string) ($saved->note ?? ''),
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
        }

        $this->isOpen = true;
    }

    public function updatedRows($value, string $name): void
    {
        if (! preg_match('/^(\d+)\.(performance_score|late_minutes|leave_days|allowed_leave_days|daily_bonus|other_money|supplement|base_salary|note)$/', $name, $matches)) {
            return;
        }

        $index = (int) $matches[1];
        $field = $matches[2];

        $this->recalculateRow($index);
    }

    public function formatMoneyField(int $index, string $field): void
    {
        if (! isset($this->rows[$index]) || ! in_array($field, ['daily_bonus', 'supplement', 'other_money'], true)) {
            return;
        }

        $this->rows[$index][$field] = $this->formatMoney($this->rows[$index][$field] ?? 0);
        $this->recalculateRow($index);
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'salaryMonth', 'rows']);
    }

    public function save(): void
    {
        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();
        $calculator = app(WaliSalaryCalculator::class);

        foreach ($this->rows as $index => $row) {
            $computed = $calculator->calculate($row, $month);

            DataSalaryZhuzhu::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'employee_id' => (int) $row['employee_id'],
                    'salary_month' => $month->toDateString(),
                ],
                [
                    'user_id' => auth()->id(),
                    'employee_id' => (int) $row['employee_id'],
                    'employee_name' => (string) $row['employee_name'],
                    'salary_month' => $month->toDateString(),
                    'base_salary' => $this->parseMoney($row['base_salary'] ?? 0),
                    'performance_score' => $this->parseMoney($row['performance_score'] ?? 0),
                    'late_minutes' => (int) $row['late_minutes'],
                    'late_days' => $computed['late_penalty_score'],
                    'leave_days' => $this->parseNumber($row['leave_days'] ?? 0),
                    'allowed_leave_days' => (int) $row['allowed_leave_days'],
                    'standard_work_days' => $computed['standard_work_days'],
                    'actual_work_days' => $computed['actual_work_days'],
                    'score' => $computed['payroll_score'],
                    'variable_salary' => $computed['variable_salary'],
                    'daily_bonus' => $this->parseMoney($row['daily_bonus'] ?? 0),
                    'supplement' => $this->parseMoney($row['supplement'] ?? 0),
                    'other_money' => $this->parseMoney($row['other_money'] ?? 0),
                    'note' => (string) $row['note'],
                    'total_salary' => $computed['total_salary'],
                    'odd_point_money' => $computed['odd_point_money'],
                    'commission' => $computed['commission'],
                    'net_received' => $computed['net_received'],
                ]
            );

            $this->rows[$index]['standard_work_days'] = $computed['standard_work_days'];
            $this->rows[$index]['actual_work_days'] = $computed['actual_work_days'];
            $this->rows[$index]['over_leave_days'] = $computed['over_leave_days'];
            $this->rows[$index]['late_penalty_score'] = $computed['late_penalty_score'];
            $this->rows[$index]['payroll_score'] = $computed['payroll_score'];
            $this->rows[$index]['variable_salary'] = $computed['variable_salary'];
            $this->rows[$index]['commission'] = $computed['commission'];
            $this->rows[$index]['odd_point_money'] = $computed['odd_point_money'];
            $this->rows[$index]['total_salary'] = $computed['total_salary'];
            $this->rows[$index]['net_received'] = $computed['net_received'];
        }

        $this->dispatch('toast', type: 'success', title: 'Da luu!', message: 'Da cap nhat tong ket thang thanh cong.');
        $this->dispatch('wali-salary-updated')->to(Wali::class);
        $this->close();
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

    private function moneyInput(mixed $value): string
    {
        $amount = $this->parseMoney($value);

        if ($amount <= 0) {
            return '';
        }

        return number_format($amount, 0, ',', '.');
    }

    private function formatMoney(mixed $value): string
    {
        $amount = $this->parseMoney($value);

        if ($amount <= 0) {
            return '';
        }

        return number_format($amount, 0, ',', '.');
    }

    private function parseNumber(mixed $value): float
    {
        return $this->parseMoney($value);
    }

    public function render(): View
    {
        return view('livewire.modals.salary.month-summary', [
            'monthLabel' => $this->salaryMonth !== ''
                ? CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->format('m/Y')
                : '',
        ]);
    }

    private function recalculateRow(int $index): void
    {
        if (! isset($this->rows[$index]) || $this->salaryMonth === '') {
            return;
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();
        $computed = app(WaliSalaryCalculator::class)->calculate($this->rows[$index], $month);

        $this->rows[$index]['standard_work_days'] = $computed['standard_work_days'];
        $this->rows[$index]['actual_work_days'] = $computed['actual_work_days'];
        $this->rows[$index]['late_penalty_score'] = $computed['late_penalty_score'];
        $this->rows[$index]['payroll_score'] = $computed['payroll_score'];
        $this->rows[$index]['variable_salary'] = $computed['variable_salary'];
        $this->rows[$index]['commission'] = $computed['commission'];
        $this->rows[$index]['odd_point_money'] = $computed['odd_point_money'];
        $this->rows[$index]['total_salary'] = $computed['total_salary'];
        $this->rows[$index]['net_received'] = $computed['net_received'];
    }
}

