<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->timestamp('in_time')->nullable();
            $table->timestamp('out_time')->nullable();
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->decimal('late_minutes', 8, 2)->default(0);
            $table->decimal('early_leaving_minutes', 8, 2)->default(0);
            $table->decimal('overtime_minutes', 8, 2)->default(0);
            $table->string('status')->default('Absent'); // Present, Absent, Half Day, Leave, Holiday
            $table->boolean('is_finalized')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendances');
    }
};
