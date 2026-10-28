<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_flags', function (Blueprint $table): void {
            $table->id();
            $table->morphs('flaggable');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['flaggable_type', 'flaggable_id', 'reason']);
            $table->index(['resolved_at', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_flags');
    }
};
