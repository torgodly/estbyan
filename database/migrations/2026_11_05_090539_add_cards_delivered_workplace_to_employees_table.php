<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The administration (workplace key) that received the cards when they were delivered to an administration.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('cards_delivered_workplace')->nullable()->after('cards_delivered_to')->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['cards_delivered_workplace']);
            $table->dropColumn('cards_delivered_workplace');
        });
    }
};
