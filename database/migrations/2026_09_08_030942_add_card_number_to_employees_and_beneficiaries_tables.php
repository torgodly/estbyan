<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('card_number', 8)->nullable()->unique()->after('employee_number');
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('card_number', 8)->nullable()->index()->after('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('card_number');
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn('card_number');
        });
    }
};
