<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSalaryZhuzhuEmployee extends Model
{
    protected $table = 'data_salary_zhuzhu_employees';

    protected $fillable = [
        'user_id',
        'employee_name',
        'base_salary',
        'allowed_leave_days',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'base_salary' => 'decimal:2',
        'allowed_leave_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function salaryRows(): HasMany
    {
        return $this->hasMany(DataSalaryZhuzhu::class, 'employee_id');
    }
}
