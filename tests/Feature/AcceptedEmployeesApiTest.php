<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Enums\Gender;
use App\Enums\ReviewFlagReason;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

const ACCEPTED_REGISTRATIONS_API_KEY = 'test-accepted-registrations-key';

function markCardsDelivered(MedicalRegistration $registration): void
{
    $hr = User::factory()->hr()->create();

    $registration->employee->markCardsDelivered($hr, CardDeliveryRecipient::Employee);
}

it('rejects requests without the custom api key', function () {
    $this->getJson('/api/accepted-employees')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthorized.']);

    $this->withHeader('X-Api-Key', 'wrong-key')
        ->getJson('/api/accepted-employees')
        ->assertUnauthorized();
});

it('rejects every request when the api key is not configured', function () {
    Config::set('services.accepted_registrations.key', '');

    $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->getJson('/api/accepted-employees')
        ->assertUnauthorized();
});

it('returns only accepted employees with their family members', function () {
    $approved = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'أحمد المقبول',
        'employee_number' => '1000',
        'gender' => Gender::Male,
    ]);
    markCardsDelivered($approved);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $approved->id,
        'full_name' => 'فاطمة أحمد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $son = Beneficiary::factory()->create([
        'medical_registration_id' => $approved->id,
        'full_name' => 'يوسف أحمد',
        'relationship' => BeneficiaryRelationship::Son,
    ]);

    $approvedNotDelivered = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'مقبول بلا تسليم',
        'employee_number' => '1500',
    ]);

    MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'سالم المعلق',
        'employee_number' => '2000',
    ]);
    MedicalRegistration::factory()->declined()->create([
        'full_name' => 'نورة المرفوضة',
        'employee_number' => '3000',
    ]);
    MedicalRegistration::factory()->editing()->create([
        'full_name' => 'ليلى قيد التعديل',
        'employee_number' => '4000',
    ]);
    $replaced = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'قديم مقبول',
        'employee_number' => '5000',
    ]);
    markCardsDelivered($replaced);
    MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $replaced->employee_id,
        'full_name' => 'قديم صار معلقاً',
        'employee_number' => '5000',
    ]);

    $response = $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->getJson('/api/accepted-employees')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'أحمد المقبول')
        ->assertJsonPath('data.0.employee_number', '1000')
        ->assertJsonPath('data.0.family_members.0.full_name', 'فاطمة أحمد')
        ->assertJsonPath('data.0.family_members.0.relationship', 'spouse')
        ->assertJsonPath('data.0.family_members.0.relationship_label', 'زوجة')
        ->assertJsonPath('data.0.family_members.1.full_name', 'يوسف أحمد')
        ->assertJsonPath('data.0.family_members.1.relationship_label', 'ابن')
        ->assertJsonPath('data.0.national_id', $approved->national_id)
        ->assertJsonPath('data.0.date_of_birth', $approved->date_of_birth?->toDateString())
        ->assertJsonPath('data.0.blood_type', $approved->blood_type?->value)
        ->assertJsonPath('data.0.blood_type_label', $approved->blood_type?->label())
        ->assertJsonPath('data.0.card_number', $approved->employee->card_number)
        ->assertJsonPath('data.0.card_number_label', $approved->employee->cardNumberLabel())
        ->assertJsonPath('data.0.family_members.0.national_id', $spouse->national_id)
        ->assertJsonPath('data.0.family_members.0.date_of_birth', $spouse->date_of_birth?->toDateString())
        ->assertJsonPath('data.0.family_members.0.blood_type', $spouse->blood_type?->value)
        ->assertJsonPath('data.0.uuid', $approved->uuid)
        ->assertJsonPath('data.0.current_step', $approved->current_step)
        ->assertJsonPath('data.0.review_note', $approved->review_note)
        ->assertJsonPath('data.0.family_members.0.medical_registration_id', $approved->id)
        ->assertJsonStructure([
            'data' => [[
                'uuid',
                'review_note',
                'reviewed_by',
                'reviewer_name',
                'current_step',
                'is_active',
                'cards_delivered_by',
                'cards_delivered_by_name',
                'created_at',
                'updated_at',
                'photo',
                'family_members' => [[
                    'medical_registration_id',
                    'photo',
                    'created_at',
                    'updated_at',
                ]],
            ]],
        ]);

    expect($response->json('data.0.family_members'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('status')->unique()->all())->toBe(['approved'])
        ->and(collect($response->json('data'))->pluck('full_name')->all())->not->toContain('مقبول بلا تسليم')
        ->and(collect($response->json('data'))->pluck('full_name')->all())->not->toContain('سالم المعلق')
        ->and(collect($response->json('data'))->pluck('full_name')->all())->not->toContain('نورة المرفوضة')
        ->and(collect($response->json('data'))->pluck('full_name')->all())->not->toContain('ليلى قيد التعديل')
        ->and(collect($response->json('data'))->pluck('full_name')->all())->not->toContain('قديم مقبول')
        ->and(collect($response->json('data'))->pluck('full_name')->all())->not->toContain('قديم صار معلقاً')
        ->and($response->json('data.0.card_number'))->toBe($approved->employee->card_number)
        ->and($response->json('data.0.family_members.0.card_number'))->toBe($spouse->card_number)
        ->and($response->json('data.0.family_members.1.id'))->toBe($son->id)
        ->and($approvedNotDelivered->employee->fresh()->cardsAreDelivered())->toBeFalse();
});

it('returns photo urls and serves the image with the same api key', function () {
    Storage::fake('local');

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    Storage::disk('local')->put('registrations/employee.png', $bytes);
    Storage::disk('local')->put('registrations/spouse.png', $bytes);

    $approved = MedicalRegistration::factory()->approved()->create([
        'employee_photo_path' => 'registrations/employee.png',
        'family_status_document_path' => 'registrations/family.pdf',
    ]);
    markCardsDelivered($approved);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $approved->id,
        'full_name' => 'فاطمة أحمد',
        'relationship' => BeneficiaryRelationship::Spouse,
        'photo_path' => 'registrations/spouse.png',
    ]);

    $response = $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->getJson('/api/accepted-employees')
        ->assertOk()
        ->assertJsonMissingPath('data.0.family_status_document');

    $employeePhoto = $response->json('data.0.photo');
    $familyPhoto = $response->json('data.0.family_members.0.photo');

    expect($employeePhoto)->toBe(route('api.accepted-employees.photo', $approved))
        ->and($familyPhoto)->toBe(route('api.accepted-employees.family-member-photo', [$approved, $spouse]));

    $this->flushHeaders();

    $this->get($employeePhoto)->assertUnauthorized();

    $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->get($employeePhoto)
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->get($familyPhoto)
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('leaves out cancelled family members and their photos', function () {
    Storage::fake('local');
    Storage::disk('local')->put('registrations/wife.png', 'photo');

    $approved = MedicalRegistration::factory()->approved()->create(['gender' => Gender::Male]);
    markCardsDelivered($approved);
    $cancelled = Beneficiary::factory()->create([
        'medical_registration_id' => $approved->id,
        'relationship' => BeneficiaryRelationship::Spouse,
        'photo_path' => 'registrations/wife.png',
    ]);
    $cancelled->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);

    $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->getJson('/api/accepted-employees')
        ->assertOk()
        ->assertJsonCount(0, 'data.0.family_members')
        ->assertJsonPath('data.0.beneficiaries_count', 0);

    $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->get(route('api.accepted-employees.family-member-photo', [$approved, $cancelled->id]))
        ->assertNotFound();
});

it('includes the office and marks people waiting for manual review', function () {
    $approved = MedicalRegistration::factory()->approved()->create();
    $approved->employee->update(['office' => 'مكتب الشؤون القانونية']);
    markCardsDelivered($approved);
    $flaggedMember = Beneficiary::factory()->create([
        'medical_registration_id' => $approved->id,
        'relationship' => BeneficiaryRelationship::Son,
    ]);
    $otherMember = Beneficiary::factory()->create([
        'medical_registration_id' => $approved->id,
        'relationship' => BeneficiaryRelationship::Daughter,
    ]);

    ReviewFlag::raise($approved->employee, ReviewFlagReason::PossibleDuplicateEmployee, 'ملاحظة');
    ReviewFlag::raise($flaggedMember, ReviewFlagReason::PossibleDuplicateFamilyMember, 'ملاحظة');
    ReviewFlag::raise($otherMember, ReviewFlagReason::PossibleDuplicateFamilyMember, 'ملاحظة')
        ->resolve(User::factory()->create());

    $response = $this->withHeader('X-Api-Key', ACCEPTED_REGISTRATIONS_API_KEY)
        ->getJson('/api/accepted-employees')
        ->assertOk()
        ->assertJsonPath('data.0.office', 'مكتب الشؤون القانونية')
        ->assertJsonPath('data.0.needs_review', true);

    $members = collect($response->json('data.0.family_members'))->keyBy('id');

    expect($members[$flaggedMember->id]['needs_review'])->toBeTrue()
        ->and($members[$otherMember->id]['needs_review'])->toBeFalse();
});
