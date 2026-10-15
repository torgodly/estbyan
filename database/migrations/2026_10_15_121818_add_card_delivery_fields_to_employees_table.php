<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->timestamp('cards_delivered_at')->nullable()->after('card_printed_at');
            $table->string('cards_delivered_to')->nullable()->after('cards_delivered_at');
            $table->foreignId('cards_delivered_by')->nullable()->after('cards_delivered_to')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cards_delivered_by');
            $table->dropColumn(['cards_delivered_at', 'cards_delivered_to']);
        });
    }
};
