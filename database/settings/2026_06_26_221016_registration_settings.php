<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('registration.form_enabled', true);
        $this->migrator->add('registration.disabled_message_ar', 'التسجيل الطبي مغلق حالياً. يرجى المحاولة لاحقاً أو التواصل مع إدارة الرعاية الذكية.');
        $this->migrator->add('registration.disabled_message_en', 'Medical registration is currently closed. Please try again later or contact Smart Care administration.');
    }
};
