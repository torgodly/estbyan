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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageRegistrationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'إعدادات';

    protected static ?string $title = 'إعدادات نموذج التسجيل';

    protected static string|\UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 3;

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

        $settings->form_enabled = (bool) ($data['form_enabled'] ?? false);
        $settings->disabled_message_ar = $data['disabled_message_ar'] ?? $settings->disabled_message_ar;
        $settings->disabled_message_en = $data['disabled_message_en'] ?? $settings->disabled_message_en;
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
                ->description('تحكّم في إتاحة نموذج التسجيل العام للموظفين.')
                ->icon(Heroicon::OutlinedPower)
                ->schema([
                    Toggle::make('form_enabled')
                        ->label(fn (Get $get): string => $get('form_enabled')
                            ? 'النموذج مفعّل الآن'
                            : 'النموذج معطّل الآن')
                        ->helperText(fn (Get $get): string => $get('form_enabled')
                            ? 'الموظفون يمكنهم فتح الرابط وتعبئة الاستبيان.'
                            : 'الزوّار سيرون صفحة الإغلاق مع الرسائل أدناه بدلاً من النموذج.')
                        ->onColor('success')
                        ->offColor('warning')
                        ->inline(false)
                        ->live(),
                ]),
            Section::make('رسائل صفحة الإغلاق')
                ->description('تظهر هذه الرسائل في صفحة «التسجيل مغلق» عندما يكون النموذج معطّلاً. العربية تظهر أولاً، والإنجليزية تحتها.')
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->visible(fn (Get $get): bool => ! $get('form_enabled'))
                ->schema([
                    Textarea::make('disabled_message_ar')
                        ->label('الرسالة بالعربية')
                        ->placeholder('مثال: التسجيل الطبي مغلق حالياً. يرجى المحاولة لاحقاً أو التواصل مع إدارة الرعاية الذكية.')
                        ->rows(4)
                        ->required(fn (Get $get): bool => ! $get('form_enabled'))
                        ->helperText('هذه الرسالة الرئيسية التي يقرأها الموظف.'),
                    Textarea::make('disabled_message_en')
                        ->label('الرسالة بالإنجليزية')
                        ->placeholder('Example: Medical registration is currently closed. Please try again later.')
                        ->rows(3)
                        ->required(fn (Get $get): bool => ! $get('form_enabled'))
                        ->helperText('تظهر تحت الرسالة العربية بخط أصغر.')
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
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
