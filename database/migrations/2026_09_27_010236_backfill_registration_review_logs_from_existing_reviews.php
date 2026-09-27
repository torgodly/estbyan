<?php

use App\Support\RegistrationReviewLogBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RegistrationReviewLogBackfill::run();
    }
};
