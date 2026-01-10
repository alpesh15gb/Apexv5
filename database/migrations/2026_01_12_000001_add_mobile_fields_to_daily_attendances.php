<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->string('in_image')->nullable()->after('in_time');
            $table->decimal('in_lat', 10, 8)->nullable()->after('in_image');
            $table->decimal('in_long', 11, 8)->nullable()->after('in_lat');

            $table->string('out_image')->nullable()->after('out_time');
            $table->decimal('out_lat', 10, 8)->nullable()->after('out_image');
            $table->decimal('out_long', 11, 8)->nullable()->after('out_lat');
        });
    }

    public function down(): void
    {
        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->dropColumn(['in_image', 'in_lat', 'in_long', 'out_image', 'out_lat', 'out_long']);
        });
    }
};
