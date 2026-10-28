<?php

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AcceptedEmployeeResource;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcceptedEmployeesController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $registrations = MedicalRegistration::query()
            ->with(['employee.cardsDeliveredBy', 'beneficiaries', 'reviewer'])
            ->where('status', RegistrationStatus::Approved->value)
            ->whereHas('employee', function ($query): void {
                $query->whereNotNull('cards_delivered_at');
            })
            ->whereIn('id', function (Builder $query): void {
                $query->selectRaw('max(id)')
                    ->from('medical_registrations')
                    ->groupBy('employee_id');
            })
            ->orderBy('employee_number')
            ->orderBy('id')
            ->get()
            ->filter(fn (MedicalRegistration $registration): bool => $registration->isApproved()
                && $registration->employee?->cardsAreDelivered() === true)
            ->values();

        $openReviewFlags = $this->openReviewFlags();

        return response()->stream(function () use ($registrations, $request, $openReviewFlags): void {
            echo '{"data":[';

            $first = true;

            foreach ($registrations as $registration) {
                if (! $first) {
                    echo ',';
                }

                $first = false;

                echo json_encode(
                    (new AcceptedEmployeeResource($registration))->withOpenReviewFlags($openReviewFlags)->resolve($request),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }

            echo ']}';
        }, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    /**
     * @return array{employees: array<int, true>, beneficiaries: array<int, true>}
     */
    private function openReviewFlags(): array
    {
        $flags = ReviewFlag::query()->open()->get(['flaggable_type', 'flaggable_id']);

        $idsOf = fn (string $morphClass): array => $flags
            ->where('flaggable_type', $morphClass)
            ->mapWithKeys(fn (ReviewFlag $flag): array => [(int) $flag->flaggable_id => true])
            ->all();

        return [
            'employees' => $idsOf((new Employee)->getMorphClass()),
            'beneficiaries' => $idsOf((new Beneficiary)->getMorphClass()),
        ];
    }
}
