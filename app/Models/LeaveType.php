<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_paid' => 'boolean',
    ];

    public function allocations()
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }
}
