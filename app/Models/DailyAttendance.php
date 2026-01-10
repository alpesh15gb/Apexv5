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
        'in_time' => 'datetime',
        'out_time' => 'datetime',
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
