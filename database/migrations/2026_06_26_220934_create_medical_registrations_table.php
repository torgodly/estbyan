<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');

            $table->string('employee_number');
            $table->string('national_id');
            $table->date('date_of_birth');
            $table->string('full_name');
            $table->timestamp('consent_at')->nullable();

            $table->string('workplace')->nullable();
            $table->string('job_title')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->unsignedTinyInteger('beneficiaries_count')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();

            $table->boolean('has_chronic_conditions')->default(false);
            $table->json('chronic_conditions')->nullable();
            $table->boolean('has_tumor')->default(false);
            $table->boolean('has_surgery_history')->default(false);
            $table->boolean('uses_medical_devices')->default(false);
            $table->boolean('hospitalized_recently')->default(false);
            $table->boolean('traveled_for_treatment')->default(false);

            $table->string('family_status_document_path')->nullable();
            $table->string('employee_photo_path')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('employee_number');
            $table->index('national_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_registrations');
    }
};
