<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSalaryZhuzhuPeriod extends Model
{
    protected $table = 'data_salary_zhuzhu_periods';

    protected $fillable = [
        'user_id',
        'salary_month',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'salary_month' => 'date',
    ];
}