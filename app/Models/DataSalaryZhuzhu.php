<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSalaryZhuzhu extends Model
{
    protected $table = 'data_salary_zhuzhu';

    protected $fillable = [
        'user_id',
        'employee_id',
        'employee_name',
        'salary_month',
        'base_salary',
        'variable_salary',
        'late_days',
        'leave_days',
        'allowed_leave_days',
        'standard_work_days',
        'actual_work_days',
        'performance_score',
        'late_minutes',
        'score',
        'daily_bonus',
        'supplement',
        'other_money',
        'note',
        'total_salary',
        'odd_point_money',
        'commission',
        'net_received',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'salary_month' => 'date',
        'base_salary' => 'decimal:2',
        'variable_salary' => 'decimal:2',
        'late_days' => 'decimal:2',
        'leave_days' => 'decimal:2',
        'allowed_leave_days' => 'integer',
        'standard_work_days' => 'decimal:2',
        'actual_work_days' => 'decimal:2',
        'performance_score' => 'decimal:2',
        'late_minutes' => 'integer',
        'score' => 'decimal:2',
        'daily_bonus' => 'decimal:2',
        'supplement' => 'decimal:2',
        'other_money' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'odd_point_money' => 'decimal:2',
        'commission' => 'decimal:2',
        'net_received' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(DataSalaryZhuzhuEmployee::class, 'employee_id');
    }
}
