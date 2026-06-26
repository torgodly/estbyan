<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class RegistrationSettings extends Settings
{
    public bool $form_enabled;

    public string $disabled_message_ar;

    public string $disabled_message_en;

    public static function group(): string
    {
        return 'registration';
    }
}
