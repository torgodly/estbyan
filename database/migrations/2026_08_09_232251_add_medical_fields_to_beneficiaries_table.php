<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->boolean('has_chronic_conditions')->default(false)->after('has_chronic_condition');
            $table->json('chronic_conditions')->nullable()->after('has_chronic_conditions');
            $table->boolean('has_tumor')->default(false)->after('chronic_conditions');
            $table->boolean('has_surgery_history')->default(false)->after('has_tumor');
            $table->boolean('uses_medical_devices')->default(false)->after('has_surgery_history');
            $table->boolean('hospitalized_recently')->default(false)->after('uses_medical_devices');
            $table->boolean('traveled_for_treatment')->default(false)->after('hospitalized_recently');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn([
                'has_chronic_conditions',
                'chronic_conditions',
                'has_tumor',
                'has_surgery_history',
                'uses_medical_devices',
                'hospitalized_recently',
                'traveled_for_treatment',
            ]);
        });
    }
};
