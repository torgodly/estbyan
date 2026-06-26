<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->string('national_id')->unique();
            $table->date('date_of_birth');
            $table->string('full_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_number', 'national_id', 'date_of_birth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
