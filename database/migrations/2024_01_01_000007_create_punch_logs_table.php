<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('punch_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('punch_time');
            $table->string('device_id')->nullable();
            $table->string('device_emp_code'); // Raw code from device, mapped to employee later if needed
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); // Linked employee
            $table->boolean('is_processed')->default(false); // If processed into daily attendance
            $table->timestamps();

            $table->index(['device_emp_code', 'punch_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punch_logs');
    }
};
