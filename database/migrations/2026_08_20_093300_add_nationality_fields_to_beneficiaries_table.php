<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->boolean('is_libyan')->default(true)->after('relationship');
            $table->string('nationality')->nullable()->after('is_libyan');
            $table->string('passport_number')->nullable()->after('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn(['is_libyan', 'nationality', 'passport_number']);
        });
    }
};
