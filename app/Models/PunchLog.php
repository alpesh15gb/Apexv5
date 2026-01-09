<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PunchLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'punch_time' => 'datetime',
        'is_processed' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
