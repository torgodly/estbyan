<?php

namespace App\Filament\Resources\Employees\Actions;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeDeleter;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Support-only: permanently delete an employee with their registrations, family members and files.
 */
class DeleteEmployeeEntirelyAction
{
    public static function make(): Action
    {
        return Action::make('deleteEmployeeEntirely')
            ->label('حذف الموظف نهائياً')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => self::canDelete())
            ->requiresConfirmation()
            ->modalHeading(fn (Employee $record): string => 'حذف '.$record->full_name.' نهائياً')
            ->modalDescription('سيُحذف الموظف وكل طلباته وكل أفراد عائلته وصورهم ومستنداتهم بشكل نهائي. لا يمكن التراجع عن ذلك.')
            ->modalSubmitActionLabel('نعم، احذف نهائياً')
            ->schema([
                TextInput::make('confirm_employee_number')
                    ->label(fn (Employee $record): string => 'للتأكيد اكتب الرقم الوظيفي: '.$record->employee_number)
                    ->required()
                    ->rule(fn (Employee $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                        if (trim((string) $value) !== (string) $record->employee_number) {
                            $fail('الرقم الوظيفي غير مطابق.');
                        }
                    }),
            ])
            ->action(function (Employee $record): void {
                abort_unless(self::canDelete(), 403);

                $name = $record->full_name;
                $result = app(EmployeeDeleter::class)->delete($record);

                Notification::make()
                    ->title('تم حذف الموظف '.$name.' نهائياً')
                    ->body(sprintf(
                        'حُذف %d طلب و%d فرد عائلة و%d ملف.',
                        $result['registrations'],
                        $result['family_members'],
                        $result['files'],
                    ))
                    ->success()
                    ->send();
            })
            ->successRedirectUrl(EmployeeResource::getUrl('index'));
    }

    private static function canDelete(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isSmartCare();
    }
}
