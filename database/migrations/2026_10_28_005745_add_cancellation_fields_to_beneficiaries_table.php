<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('card_printed_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
