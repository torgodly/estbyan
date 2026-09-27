<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['medical_registration_id', 'created_at'], 'rrl_registration_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_review_logs');
    }
};
