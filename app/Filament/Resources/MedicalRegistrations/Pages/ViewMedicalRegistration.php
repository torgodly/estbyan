<?php

namespace App\Filament\Resources\MedicalRegistrations\Pages;

use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewMedicalRegistration extends ViewRecord
{
    protected static string $resource = MedicalRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الموظف')
                ->columns(2)
                ->schema([
                    TextEntry::make('full_name')->label('الاسم'),
                    TextEntry::make('employee_number')->label('الرقم الوظيفي'),
                    TextEntry::make('national_id')->label('الرقم الوطني'),
                    TextEntry::make('date_of_birth')->label('تاريخ الميلاد')->date(),
                    TextEntry::make('status')->label('الحالة')->badge(),
                    TextEntry::make('phone')->label('الهاتف'),
                    TextEntry::make('email')->label('البريد'),
                    TextEntry::make('workplace')
                        ->label('مكان العمل')
                        ->formatStateUsing(fn ($record) => $record->workplaceLabel() ?? '—'),
                    TextEntry::make('city')
                        ->label('المدينة')
                        ->formatStateUsing(fn ($record) => $record->cityLabel() ?? '—'),
                    TextEntry::make('address')->label('العنوان')->columnSpanFull(),
                ]),
            Section::make('السجل الطبي')
                ->columns(2)
                ->schema([
                    IconEntry::make('has_chronic_conditions')->label('أمراض مزمنة')->boolean(),
                    IconEntry::make('has_tumor')->label('أورام')->boolean(),
                    IconEntry::make('has_surgery_history')->label('عمليات')->boolean(),
                    IconEntry::make('uses_medical_devices')->label('أجهزة طبية')->boolean(),
                    IconEntry::make('hospitalized_recently')->label('إقامة مستشفى')->boolean(),
                    IconEntry::make('traveled_for_treatment')->label('علاج بالخارج')->boolean(),
                ]),
            Section::make('التوقيت')
                ->schema([
                    TextEntry::make('submitted_at')->label('تاريخ الإرسال')->dateTime(),
                    TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime(),
                    TextEntry::make('reference_number')->label('رقم المرجع')->copyable(),
                ]),
        ]);
    }
}
