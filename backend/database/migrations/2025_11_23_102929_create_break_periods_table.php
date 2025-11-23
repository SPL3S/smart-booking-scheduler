<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('break_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('working_hour_id')->constrained()->onDelete('cascade');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Prevent duplicate break periods for the same working hour
            $table->unique(['working_hour_id', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_periods');
    }
};
