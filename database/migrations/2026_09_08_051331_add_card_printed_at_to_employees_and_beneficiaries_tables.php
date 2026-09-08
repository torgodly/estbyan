<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->timestamp('card_printed_at')->nullable()->index()->after('card_number');
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->timestamp('card_printed_at')->nullable()->index()->after('card_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('card_printed_at');
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn('card_printed_at');
        });
    }
};
