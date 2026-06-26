<?php

namespace App\Filament\Pages;

use App\Settings\RegistrationSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageRegistrationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'إعدادات التسجيل';

    protected static ?string $title = 'إعدادات نموذج التسجيل';

    protected static string|\UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(RegistrationSettings::class);

        $this->form->fill([
            'form_enabled' => $settings->form_enabled,
            'disabled_message_ar' => $settings->disabled_message_ar,
            'disabled_message_en' => $settings->disabled_message_en,
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(RegistrationSettings::class);

        $settings->form_enabled = (bool) $data['form_enabled'];
        $settings->disabled_message_ar = $data['disabled_message_ar'];
        $settings->disabled_message_en = $data['disabled_message_en'];
        $settings->save();

        Notification::make()
            ->success()
            ->title('تم حفظ الإعدادات')
            ->body($settings->form_enabled ? 'النموذج مفعّل ومتاح للموظفين.' : 'النموذج معطّل — لن يتمكن الموظفون من الوصول إليه.')
            ->send();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('حالة النموذج')
                ->description('فعّل أو عطّل نموذج التسجيل الطبي العام للموظفين.')
                ->schema([
                    Toggle::make('form_enabled')
                        ->label('تفعيل نموذج التسجيل')
                        ->helperText('عند التعطيل، يرى الزوار رسالة إغلاق بدلاً من النموذج.')
                        ->live(),
                ]),
            Section::make('رسالة الإغلاق')
                ->description('تُعرض عندما يكون النموذج معطّلاً.')
                ->schema([
                    Textarea::make('disabled_message_ar')
                        ->label('الرسالة بالعربية')
                        ->rows(3)
                        ->required(),
                    Textarea::make('disabled_message_en')
                        ->label('الرسالة بالإنجليزية')
                        ->rows(2)
                        ->required(),
                ])
                ->visible(fn (callable $get): bool => ! $get('form_enabled')),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('registration-settings-form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('حفظ الإعدادات')
                            ->submit('save'),
                    ])->key('form-actions'),
                ]),
        ]);
    }
}
