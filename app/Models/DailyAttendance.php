<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyAttendance extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        // in_time and out_time are NOT cast to datetime to prevent timezone conversion
        // The DB stores IST wall-clock times as strings, we keep them as-is
        'in_lat' => 'decimal:8',
        'in_long' => 'decimal:8',
        'out_lat' => 'decimal:8',
        'out_long' => 'decimal:8',
        'is_finalized' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
