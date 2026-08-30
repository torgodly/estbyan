<?php

namespace App\Livewire;

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\RegistrationStatus;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Rules\LibyanNationalId;
use App\Support\LibyanNationalId as LibyanNationalIdSupport;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.registration')]
#[Title('التسجيل الطبي — مصلحة الضرائب × SMART CARE')]
class MedicalRegistrationForm extends Component
{
    use WithFileUploads;

    public int $step = 1;

    #[Locked]
    public ?int $registrationId = null;

    public string $employeeNumber = '';

    public string $nationalId = '';

    public string $dateOfBirth = '';

    public bool $consent = false;

    public string $fullName = '';

    public string $verifiedFullName = '';

    public string $workplace = '';

    public string $office = '';

    public string $jobTitle = 'employee';

    public string $gender = 'male';

    public string $maritalStatus = 'married';

    public string $beneficiariesCount = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $city = '';

    public string $address = '';

    public bool $hasChronicConditions = false;

    /** @var array<int, string> */
    public array $chronicConditions = [];

    public bool $hasTumor = false;

    public bool $hasSurgeryHistory = false;

    public bool $usesMedicalDevices = false;

    public bool $hospitalizedRecently = false;

    public bool $traveledForTreatment = false;

    /** @var array<int, array<string, mixed>> */
    public array $beneficiaries = [];

    public bool $showBeneficiaryForm = false;

    public string $beneficiaryName = '';

    public string $beneficiaryRelationship = 'spouse';

    public bool $beneficiaryIsLibyan = true;

    public string $beneficiaryNationality = '';

    public string $beneficiaryNationalId = '';

    public string $beneficiaryPassportNumber = '';

    public string $beneficiaryDateOfBirth = '';

    public string $beneficiaryBloodType = 'a_positive';

    public bool $beneficiaryHasChronicConditions = false;

    /** @var array<int, string> */
    public array $beneficiaryChronicConditions = [];

    public bool $beneficiaryHasTumor = false;

    public bool $beneficiaryHasSurgeryHistory = false;

    public bool $beneficiaryUsesMedicalDevices = false;

    public bool $beneficiaryHospitalizedRecently = false;

    public bool $beneficiaryTraveledForTreatment = false;

    public $beneficiaryPhoto = null;

    public ?string $beneficiaryExistingPhotoPath = null;

    public ?int $editingBeneficiaryIndex = null;

    public $familyStatusDocument = null;

    public $employeePhoto = null;

    public ?string $familyStatusDocumentName = null;

    public ?string $employeePhotoName = null;

    public bool $submitted = false;

    public string $referenceNumber = '';

    public bool $hasFamilyDocument = false;

    public bool $hasEmployeePhoto = false;

    public ?string $toastMessage = null;

    public bool $hasSavedDraft = false;

    public bool $identityLocked = false;

    public bool $approvedLocked = false;

    public string $approvedMessage = '';

    public function mount(): void
    {
        // Never carry a previous toast into a fresh page load / refresh.
        $this->toastMessage = null;

        if (session('registration_gate_passed')) {
            $this->restoreFromSession();

            return;
        }

        if ($draft = session('registration_step1')) {
            $this->nationalId = $draft['national_id'] ?? '';
            $this->consent = (bool) ($draft['consent'] ?? false);
            $this->step = 1;
            $this->hasSavedDraft = true;
        }
    }

    public function updated(mixed $property): void
    {
        if ($this->submitted || $this->approvedLocked) {
            return;
        }

        if ($property === 'hasChronicConditions' && ! $this->hasChronicConditions) {
            $this->chronicConditions = [];
        }

        if ($property === 'beneficiaryHasChronicConditions' && ! $this->beneficiaryHasChronicConditions) {
            $this->beneficiaryChronicConditions = [];
        }

        if ($property === 'maritalStatus') {
            $this->syncBeneficiaryRelationshipToMaritalStatus();
        }

        if ($property === 'beneficiaryRelationship') {
            $this->syncBeneficiaryCitizenshipToRelationship();
        }

        if ($property === 'beneficiaryIsLibyan') {
            $this->syncBeneficiaryIdentityFieldsToCitizenship();
        }

        if ($this->isStepOneField($property) && ! $this->hasRegistrationSession()) {
            $this->persistStepOneDraft();

            return;
        }

        if ($this->hasRegistrationSession() && $this->isAutoPersistField($property)) {
            $this->autoPersistToDatabase();
        }
    }

    public function dismissToast(): void
    {
        $this->toastMessage = null;
    }

    public function clearForm(): void
    {
        $registration = $this->registration();

        if ($registration && $registration->isEditableByEmployee()) {
            if ($registration->family_status_document_path) {
                RegistrationDocuments::disk()->delete($registration->family_status_document_path);
            }

            if ($registration->employee_photo_path) {
                RegistrationDocuments::disk()->delete($registration->employee_photo_path);
            }

            foreach ($registration->beneficiaries as $beneficiary) {
                if ($beneficiary->photo_path) {
                    RegistrationDocuments::disk()->delete($beneficiary->photo_path);
                }
            }

            RegistrationDocuments::disk()->deleteDirectory("registrations/{$registration->uuid}");
            $registration->beneficiaries()->delete();
            $registration->delete();
        }

        session()->forget([
            'registration_id',
            'registration_step1',
            'reference_download_id',
            'registration_editing',
            'registration_gate_passed',
        ]);

        $this->resetFormState();
        $this->toastMessage = 'تم مسح جميع البيانات. يمكنك البدء من جديد.';
    }

    public function logout(): void
    {
        session()->forget([
            'registration_id',
            'registration_step1',
            'reference_download_id',
            'registration_editing',
            'registration_gate_passed',
        ]);

        $this->resetFormState();
        $this->toastMessage = 'تم تسجيل الخروج بنجاح';
    }

    public function verifyIdentity(): void
    {
        $this->throttleIdentityVerification();

        $this->validateRules([
            'nationalId' => ['required', 'string', new LibyanNationalId],
            'consent' => ['accepted'],
        ], [
            'nationalId.required' => 'الرقم الوطني مطلوب',
            'consent.accepted' => 'يجب الموافقة على سياسة الخصوصية للمتابعة',
        ]);

        $employee = Employee::findForVerification($this->nationalId);

        if (! $employee) {
            $this->addVisibleError('nationalId', 'لم يتم العثور على موظف بهذا الرقم الوطني.');

            return;
        }

        $this->employeeNumber = $employee->employee_number;

        $genderFromNid = LibyanNationalIdSupport::gender($employee->national_id)->value;

        $existing = MedicalRegistration::query()
            ->with('beneficiaries')
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        if ($existing?->isApproved()) {
            $this->approvedLocked = true;
            $this->approvedMessage = 'تم اعتماد طلبك مسبقاً ولا يمكن تعديله.'.($existing->reference_number ? ' رقم المرجع: '.$existing->reference_number : '');
            $this->referenceNumber = $existing->reference_number ?? '';
            $this->registrationId = $existing->id;
            session([
                'registration_id' => $existing->id,
                'reference_download_id' => $existing->id,
                'registration_gate_passed' => true,
            ]);

            return;
        }

        if ($existing) {
            $existing->update([
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'national_id' => $employee->national_id,
                'workplace' => $existing->workplace ?: $employee->workplace,
                'gender' => $genderFromNid,
                'consent_at' => $existing->consent_at ?? now(),
            ]);

            $existing = $existing->fresh('beneficiaries');
            $this->loadRegistration($existing);
            $this->identityLocked = true;
            $this->gender = $genderFromNid;
            $this->syncDirectoryFromEmployee($employee);
            session([
                'registration_id' => $existing->id,
                'registration_gate_passed' => true,
            ]);
            session()->forget('registration_step1');

            if ($existing->isSubmitted()) {
                $this->showSubmittedSuccess($existing, notify: true);

                return;
            }

            if ($existing->isEditing()) {
                $this->resumeEditingSubmittedRegistration($existing);
                $this->notify('تم استعادة طلبك — أكمل التعديل ثم أعد الإرسال');

                return;
            }

            if (filled($existing->reference_number)) {
                session(['reference_download_id' => $existing->id]);
                $this->notify('تم استعادة طلبك السابق — يمكنك التعديل مع الاحتفاظ برقم المرجع');
            } else {
                $this->notify('تم التحقق من بياناتك — تابع إكمال التسجيل');
            }

            return;
        }

        $registration = MedicalRegistration::query()->create([
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'national_id' => $employee->national_id,
            'full_name' => $employee->full_name,
            'workplace' => $employee->workplace,
            'gender' => $genderFromNid,
            'status' => RegistrationStatus::Draft,
            'consent_at' => now(),
            'current_step' => 2,
        ]);

        $this->loadRegistration($registration);
        $this->identityLocked = true;
        $this->gender = $genderFromNid;
        $this->syncDirectoryFromEmployee($employee);
        $this->step = 2;
        session([
            'registration_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        session()->forget('registration_step1');
        $this->notify('تم التحقق من بياناتك — تابع إكمال التسجيل');
    }

    public function saveEmployeeDetails(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $this->gender = LibyanNationalIdSupport::isValid($this->nationalId)
            ? LibyanNationalIdSupport::gender($this->nationalId)->value
            : $this->gender;

        $this->validateRules([
            'dateOfBirth' => [
                'required',
                'date',
                'before:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! LibyanNationalIdSupport::matchesDateOfBirth($this->nationalId, $value)) {
                        $year = LibyanNationalIdSupport::isValid($this->nationalId)
                            ? (string) LibyanNationalIdSupport::birthYear($this->nationalId)
                            : '—';
                        $fail('سنة تاريخ الميلاد يجب أن تطابق السنة في الرقم الوطني ('.$year.').');
                    }
                },
            ],
            'workplace' => ['required', Rule::in(array_keys(config('registration.workplaces')))],
            'jobTitle' => ['nullable', Rule::in(array_keys(config('registration.job_titles')))],
            'gender' => ['required', Rule::in(array_map(fn (Gender $g) => $g->value, Gender::cases()))],
            'maritalStatus' => ['required', Rule::in(array_map(fn (MaritalStatus $s) => $s->value, MaritalStatus::cases()))],
            'phone' => ['required', 'string', 'min:9', 'max:15'],
            'whatsapp' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['required', Rule::in(array_keys(config('registration.cities')))],
            'address' => ['required', 'string', 'max:500'],
        ], [
            'dateOfBirth.required' => 'تاريخ الميلاد مطلوب',
            'workplace.required' => 'مكان العمل مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'city.required' => 'المدينة مطلوبة',
            'address.required' => 'العنوان السكني مطلوب',
            'email.email' => 'أدخل بريداً إلكترونياً صحيحاً (مثل name@example.com) أو اترك الحقل فارغاً',
        ]);

        $this->autoPersistToDatabase();
        $this->goToStep(3);
    }

    public function saveMedicalRecord(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $this->validateRules([
            'chronicConditions' => [Rule::requiredIf($this->hasChronicConditions), 'array'],
        ], [
            'chronicConditions.required' => 'يرجى تحديد الأمراض المزمنة على الأقل',
        ]);

        $this->autoPersistToDatabase();
        $this->goToStep(4);
    }

    public function toggleBeneficiaryForm(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $this->showBeneficiaryForm = ! $this->showBeneficiaryForm;
        $this->resetBeneficiaryForm();
    }

    public function saveBeneficiary(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $isLibyan = $this->beneficiaryIsLibyanForCurrentRelationship();

        $rules = [
            'beneficiaryName' => ['required', 'string', 'max:255'],
            'beneficiaryRelationship' => [
                'required',
                Rule::in(array_map(
                    fn (BeneficiaryRelationship $r) => $r->value,
                    $this->availableBeneficiaryRelationships(),
                )),
            ],
            'beneficiaryBloodType' => ['required', Rule::in(array_map(fn (BloodType $b) => $b->value, BloodType::cases()))],
            'beneficiaryChronicConditions' => [Rule::requiredIf($this->beneficiaryHasChronicConditions), 'array'],
            'beneficiaryPhoto' => [
                Rule::requiredIf($this->editingBeneficiaryIndex === null && blank($this->beneficiaryExistingPhotoPath)),
                'nullable',
                'file',
                'mimes:'.implode(',', RegistrationDocuments::photoMimes()),
                'max:'.RegistrationDocuments::photoMaxKilobytes(),
            ],
        ];

        if ($isLibyan) {
            $rules['beneficiaryNationalId'] = ['required', 'string', new LibyanNationalId];
            $rules['beneficiaryDateOfBirth'] = [
                'required',
                'date',
                'before:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! LibyanNationalIdSupport::matchesDateOfBirth($this->beneficiaryNationalId, $value)) {
                        $fail('سنة ميلاد المستفيد يجب أن تطابق السنة في رقمه الوطني.');
                    }
                },
            ];
        } else {
            $rules['beneficiaryNationality'] = ['required', Rule::in(array_keys(config('registration.nationalities', [])))];
            $rules['beneficiaryPassportNumber'] = ['required', 'string', 'min:5', 'max:40', 'regex:/^[A-Za-z0-9\\/-]+$/'];
            $rules['beneficiaryDateOfBirth'] = ['required', 'date', 'before:today'];
        }

        $this->validateRules($rules, array_merge($this->documentValidationMessages(), [
            'beneficiaryName.required' => 'اسم المستفيد مطلوب',
            'beneficiaryRelationship.in' => $this->beneficiaryRelationshipValidationMessage(),
            'beneficiaryNationalId.required' => 'الرقم الوطني للمستفيد مطلوب',
            'beneficiaryNationality.required' => 'الجنسية مطلوبة للمستفيد غير الليبي',
            'beneficiaryNationality.in' => 'الجنسية المختارة غير صالحة',
            'beneficiaryPassportNumber.required' => 'رقم جواز السفر مطلوب للمستفيد غير الليبي',
            'beneficiaryPassportNumber.regex' => 'رقم جواز السفر يجب أن يحتوي على أحرف وأرقام فقط',
            'beneficiaryDateOfBirth.required' => 'تاريخ ميلاد المستفيد مطلوب',
            'beneficiaryPhoto.required' => 'صورة المستفيد مطلوبة',
            'beneficiaryChronicConditions.required' => 'يرجى تحديد الأمراض المزمنة على الأقل',
        ]));

        $employeeGender = $this->employeeGender();
        $relationship = BeneficiaryRelationship::from($this->beneficiaryRelationship);
        $relationshipLabel = $relationship->label($employeeGender);
        $expectedGender = $relationship->expectedGender($employeeGender);

        if ($relationship === BeneficiaryRelationship::Spouse) {
            $maxSpouses = BeneficiaryRelationship::maxSpousesFor($employeeGender);
            $spouseCount = $this->spouseCount($this->editingBeneficiaryIndex);

            if ($spouseCount >= $maxSpouses) {
                $this->failValidation([
                    'beneficiaryRelationship' => $this->spouseLimitMessage($employeeGender, $maxSpouses),
                ]);
            }

            if (
                $employeeGender === Gender::Female
                && ! $isLibyan
                && $this->hasLibyanChildren($this->editingBeneficiaryIndex)
            ) {
                $this->failValidation([
                    'beneficiaryIsLibyan' => 'لا يمكن تسجيل الزوج كغير ليبي بينما يوجد أبناء ليبيون. عدّل الأبناء أولاً إلى غير ليبيين بجواز السفر.',
                ]);
            }
        }

        if ($relationship->isChild() && $this->hasNonLibyanHusband() && $isLibyan) {
            $this->failValidation([
                'beneficiaryIsLibyan' => 'لأن الزوج غير ليبي لا يمكن تسجيل الأبناء كليبيين — أدخل الجنسية ورقم جواز السفر.',
            ]);
        }

        if (
            $isLibyan
            && $expectedGender !== null
            && LibyanNationalIdSupport::isValid($this->beneficiaryNationalId)
            && ! LibyanNationalIdSupport::matchesGender($this->beneficiaryNationalId, $expectedGender)
        ) {
            $digit = $expectedGender === Gender::Male ? '1' : '2';
            $genderLabel = $expectedGender === Gender::Male ? 'ذكر' : 'أنثى';

            $this->failValidation([
                'beneficiaryNationalId' => "الرقم الوطني لـ{$relationshipLabel} يجب أن يبدأ بـ {$digit} ({$genderLabel}).",
            ]);
        }

        $registration = $this->registration();

        if (! $registration) {
            $this->failValidation([
                'beneficiaryName' => 'انتهت الجلسة. يرجى التحقق من الهوية مجدداً ثم أعد المحاولة.',
            ]);
        }

        $photoPath = $this->beneficiaryExistingPhotoPath;

        if ($this->beneficiaryPhoto instanceof TemporaryUploadedFile) {
            $photoPath = $this->beneficiaryPhoto->store(
                "registrations/{$registration->uuid}/beneficiaries",
                RegistrationDocuments::diskName(),
            );
        }

        if (blank($photoPath)) {
            $this->failValidation([
                'beneficiaryPhoto' => 'صورة المستفيد مطلوبة',
            ]);
        }

        $data = [
            'full_name' => $this->beneficiaryName,
            'relationship' => $this->beneficiaryRelationship,
            'is_libyan' => $isLibyan,
            'nationality' => $isLibyan ? null : $this->beneficiaryNationality,
            'national_id' => $isLibyan ? $this->beneficiaryNationalId : null,
            'passport_number' => $isLibyan ? null : strtoupper(trim($this->beneficiaryPassportNumber)),
            'date_of_birth' => $this->beneficiaryDateOfBirth ?: null,
            'blood_type' => $this->beneficiaryBloodType,
            'has_chronic_condition' => $this->beneficiaryHasChronicConditions,
            'has_chronic_conditions' => $this->beneficiaryHasChronicConditions,
            'chronic_conditions' => $this->beneficiaryHasChronicConditions ? $this->beneficiaryChronicConditions : [],
            'has_tumor' => $this->beneficiaryHasTumor,
            'has_surgery_history' => $this->beneficiaryHasSurgeryHistory,
            'uses_medical_devices' => $this->beneficiaryUsesMedicalDevices,
            'hospitalized_recently' => $this->beneficiaryHospitalizedRecently,
            'traveled_for_treatment' => $this->beneficiaryTraveledForTreatment,
            'photo_path' => $photoPath,
        ];

        if ($this->editingBeneficiaryIndex !== null) {
            $this->beneficiaries[$this->editingBeneficiaryIndex] = array_merge(
                $this->beneficiaries[$this->editingBeneficiaryIndex],
                $data,
            );
        } else {
            $this->beneficiaries[] = $data;
        }

        $this->syncBeneficiariesToDatabase();
        $this->showBeneficiaryForm = false;
        $this->resetBeneficiaryForm();
        $this->notify('تم حفظ المستفيد');
    }

    public function editBeneficiary(int $index): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $beneficiary = $this->beneficiaries[$index] ?? null;

        if (! $beneficiary) {
            return;
        }

        $this->editingBeneficiaryIndex = $index;
        $this->beneficiaryName = $beneficiary['full_name'];
        $this->beneficiaryRelationship = $beneficiary['relationship'];
        $this->beneficiaryIsLibyan = (bool) ($beneficiary['is_libyan'] ?? true);
        $this->beneficiaryNationality = $beneficiary['nationality'] ?? '';
        $this->beneficiaryNationalId = $beneficiary['national_id'] ?? '';
        $this->beneficiaryPassportNumber = $beneficiary['passport_number'] ?? '';
        $this->beneficiaryDateOfBirth = $beneficiary['date_of_birth'] ?? '';
        $this->beneficiaryBloodType = $beneficiary['blood_type'];
        $this->beneficiaryHasChronicConditions = (bool) ($beneficiary['has_chronic_conditions'] ?? $beneficiary['has_chronic_condition'] ?? false);
        $this->beneficiaryChronicConditions = $beneficiary['chronic_conditions'] ?? [];
        $this->beneficiaryHasTumor = (bool) ($beneficiary['has_tumor'] ?? false);
        $this->beneficiaryHasSurgeryHistory = (bool) ($beneficiary['has_surgery_history'] ?? false);
        $this->beneficiaryUsesMedicalDevices = (bool) ($beneficiary['uses_medical_devices'] ?? false);
        $this->beneficiaryHospitalizedRecently = (bool) ($beneficiary['hospitalized_recently'] ?? false);
        $this->beneficiaryTraveledForTreatment = (bool) ($beneficiary['traveled_for_treatment'] ?? false);
        $this->beneficiaryExistingPhotoPath = $beneficiary['photo_path'] ?? null;
        $this->beneficiaryPhoto = null;
        $this->showBeneficiaryForm = true;
        $this->syncBeneficiaryCitizenshipToRelationship();
    }

    public function deleteBeneficiary(int $index): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $beneficiary = $this->beneficiaries[$index] ?? null;

        if ($beneficiary && ! empty($beneficiary['photo_path'])) {
            RegistrationDocuments::disk()->delete($beneficiary['photo_path']);
        }

        unset($this->beneficiaries[$index]);
        $this->beneficiaries = array_values($this->beneficiaries);
        $this->syncBeneficiariesToDatabase();
    }

    public function continueFromBeneficiaries(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $allowed = array_map(
            fn (BeneficiaryRelationship $relationship): string => $relationship->value,
            BeneficiaryRelationship::availableFor($this->maritalStatus),
        );

        $hasInvalid = collect($this->beneficiaries)->contains(
            fn (array $beneficiary): bool => ! in_array($beneficiary['relationship'] ?? '', $allowed, true),
        );

        if ($hasInvalid) {
            $this->addVisibleError(
                'beneficiaries',
                $this->maritalStatus === MaritalStatus::Single->value
                    ? 'الحالة أعزب — يرجى حذف المستفيدين من غير الوالدين قبل المتابعة'
                    : 'يوجد مستفيدون بصلة قرابة غير صالحة',
            );

            return;
        }

        $employeeGender = $this->employeeGender();
        $maxSpouses = BeneficiaryRelationship::maxSpousesFor($employeeGender);

        if ($this->spouseCount() > $maxSpouses) {
            $this->addVisibleError('beneficiaries', $this->spouseLimitMessage($employeeGender, $maxSpouses));

            return;
        }

        if ($this->hasNonLibyanHusband() && $this->hasLibyanChildren()) {
            $this->addVisibleError(
                'beneficiaries',
                'لأن الزوج غير ليبي لا يمكن أن يكون الأبناء ليبيين — عدّل كل ابن/ابنة وأدخل الجنسية ورقم جواز السفر',
            );

            return;
        }

        $this->goToStep(5);
    }

    public function saveDocuments(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $registration = $this->registration();

        if (! $registration) {
            $this->addVisibleError('employeePhoto', 'انتهت الجلسة. يرجى التحقق من الهوية مجدداً ثم أعد المحاولة.');

            return;
        }

        $rules = [];

        if ($this->familyStatusDocument !== null || blank($registration->family_status_document_path)) {
            $rules['familyStatusDocument'] = RegistrationDocuments::familyValidationRules();
        }

        if ($this->employeePhoto !== null || blank($registration->employee_photo_path)) {
            $rules['employeePhoto'] = RegistrationDocuments::photoValidationRules();
        }

        $this->validateRules($rules, $this->documentValidationMessages());

        $this->persistFamilyStatusDocument($registration);
        $this->persistEmployeePhoto($registration);

        $registration->refresh();

        if (blank($registration->family_status_document_path)) {
            $this->addVisibleError('familyStatusDocument', 'صورة من شهادة الوضع العائلي مطلوبة');

            return;
        }

        if (blank($registration->employee_photo_path)) {
            $this->addVisibleError('employeePhoto', 'الصورة الشخصية للموظف مطلوبة');

            return;
        }

        $this->hasFamilyDocument = true;
        $this->hasEmployeePhoto = true;
        $this->goToStep(6);
    }

    public function updatedFamilyStatusDocument(): void
    {
        $this->storeUploadedDocument('familyStatusDocument');
    }

    public function updatedEmployeePhoto(): void
    {
        $this->storeUploadedDocument('employeePhoto');
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        if ($this->hasRegistrationSession()) {
            $this->autoPersistToDatabase();
        } elseif ($this->step === 1) {
            $this->persistStepOneDraft();
        }

        $this->hasSavedDraft = true;
        $this->notify('تم حفظ التقديم — يمكنك المتابعة لاحقاً');
    }

    public function submitRegistration(): void
    {
        if ($this->submitted || $this->approvedLocked) {
            return;
        }

        $registration = $this->registration();

        if (
            ! $registration
            || (! $registration->family_status_document_path && ! $this->hasFamilyDocument)
            || (! $registration->employee_photo_path && ! $this->hasEmployeePhoto)
        ) {
            $this->addVisibleError('submit', 'يرجى إرفاق صورة من شهادة الوضع العائلي والصورة الشخصية قبل الإرسال');

            return;
        }

        $missingBeneficiaryPhoto = collect($this->beneficiaries)->contains(
            fn (array $beneficiary): bool => blank($beneficiary['photo_path'] ?? null),
        );

        if ($missingBeneficiaryPhoto) {
            $this->addVisibleError('submit', 'يجب إرفاق صورة لكل مستفيد قبل الإرسال');

            return;
        }

        if (! $registration->isEditableByEmployee()) {
            $this->addVisibleError('submit', 'لا يمكن تعديل طلب معتمد');

            return;
        }

        DB::transaction(function () use ($registration): void {
            $locked = MedicalRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reference = $locked->reference_number ?: MedicalRegistration::generateReferenceNumber();

            $locked->update([
                'status' => RegistrationStatus::Submitted,
                'submitted_at' => now(),
                'reference_number' => $reference,
                'review_note' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);
        });

        $registration = $registration->fresh();
        $this->referenceNumber = $registration->reference_number ?? '';
        $this->submitted = true;
        session([
            'registration_id' => $registration->id,
            'reference_download_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        session()->forget(['registration_step1', 'registration_editing']);
    }

    public function editSubmittedRegistration(): void
    {
        $registration = $this->registration();

        if (! $registration || ! $registration->isEditableByEmployee()) {
            return;
        }

        $registration->update([
            'status' => RegistrationStatus::Editing,
        ]);

        $this->submitted = false;
        $this->loadRegistration($registration->fresh()->load('beneficiaries'));
        $this->identityLocked = true;
        $this->goToStep(2);
        session([
            'registration_id' => $registration->id,
            'registration_editing' => true,
            'registration_gate_passed' => true,
        ]);
        $this->notify('يمكنك تعديل بياناتك ثم إعادة الإرسال مع الاحتفاظ برقم المرجع');
    }

    public function goBack(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $minimumStep = ($this->identityLocked || $this->registrationId) ? 2 : 1;

        if ($this->step > $minimumStep) {
            $this->goToStep($this->step - 1);
        }
    }

    public function render()
    {
        return view('livewire.medical-registration-form', [
            'workplaces' => config('registration.workplaces'),
            'jobTitles' => config('registration.job_titles'),
            'cities' => config('registration.cities'),
            'nationalities' => $this->orderedNationalities(),
            'chronicConditionOptions' => config('registration.chronic_conditions'),
            'employeeGender' => $this->employeeGender(),
            'availableBeneficiaryRelationships' => $this->availableBeneficiaryRelationships(),
            'maxSpouses' => BeneficiaryRelationship::maxSpousesFor($this->employeeGender()),
            'spouseLabel' => BeneficiaryRelationship::Spouse->label($this->employeeGender()),
            'childrenMustBeNonLibyan' => $this->childrenMustBeNonLibyan(),
            'totalSteps' => 6,
            'stepLabels' => [
                1 => 'التحقق',
                2 => 'بيانات الموظف',
                3 => 'السجل الطبي',
                4 => 'المستفيدون',
                5 => 'المستندات',
                6 => 'المراجعة',
            ],
        ]);
    }

    protected function goToStep(int $step): void
    {
        $this->step = $step;

        if ($this->hasRegistrationSession()) {
            $this->registration()?->update(['current_step' => $step]);
        }
    }

    protected function hasRegistrationSession(): bool
    {
        return is_numeric(session('registration_id'));
    }

    protected function throttleIdentityVerification(): void
    {
        $ipKey = 'registration-verify:ip:'.request()->ip();
        $nationalIdKey = 'registration-verify:nid:'.preg_replace('/\D+/', '', $this->nationalId);

        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $this->failValidation([
                'nationalId' => 'محاولات تحقق كثيرة من هذا الجهاز. يرجى الانتظار '.RateLimiter::availableIn($ipKey).' ثانية.',
            ]);
        }

        if ($nationalIdKey !== 'registration-verify:nid:' && RateLimiter::tooManyAttempts($nationalIdKey, 5)) {
            $this->failValidation([
                'nationalId' => 'محاولات كثيرة لهذا الرقم الوطني. يرجى المحاولة لاحقاً.',
            ]);
        }

        RateLimiter::hit($ipKey, 60);
        RateLimiter::hit($nationalIdKey, 900);
    }

    protected function restoreFromSession(): void
    {
        if ($id = session('registration_id')) {
            $registration = MedicalRegistration::query()
                ->with('beneficiaries')
                ->find($id);

            if ($registration?->isApproved()) {
                $this->approvedLocked = true;
                $this->approvedMessage = 'تم اعتماد طلبك مسبقاً ولا يمكن تعديله.'.($registration->reference_number ? ' رقم المرجع: '.$registration->reference_number : '');
                $this->referenceNumber = $registration->reference_number ?? '';
                $this->registrationId = $registration->id;
                $this->verifiedFullName = $registration->full_name;
                $this->fullName = $registration->full_name;
                $this->nationalId = $registration->national_id;
                $this->employeeNumber = $registration->employee_number;

                return;
            }

            if ($registration?->isSubmitted()) {
                $this->loadRegistration($registration);
                $this->showSubmittedSuccess($registration, notify: false);

                return;
            }

            if ($registration?->isEditing()) {
                $this->resumeEditingSubmittedRegistration($registration);

                return;
            }

            if ($registration && $registration->isEditableByEmployee()) {
                $this->loadRegistration($registration);
                $this->identityLocked = true;
                $this->hasSavedDraft = true;
                session()->forget('registration_editing');

                return;
            }
        }
    }

    protected function persistStepOneDraft(): void
    {
        session([
            'registration_step1' => [
                'national_id' => $this->nationalId,
                'consent' => $this->consent,
            ],
        ]);

        $this->hasSavedDraft = true;
    }

    protected function isStepOneField(string $property): bool
    {
        return in_array($property, ['nationalId', 'consent'], true);
    }

    protected function isAutoPersistField(string $property): bool
    {
        return in_array($property, [
            'dateOfBirth', 'workplace', 'jobTitle', 'gender', 'maritalStatus',
            'phone', 'whatsapp', 'email', 'city', 'address',
            'hasChronicConditions', 'chronicConditions', 'hasTumor', 'hasSurgeryHistory',
            'usesMedicalDevices', 'hospitalizedRecently', 'traveledForTreatment',
        ], true);
    }

    protected function autoPersistToDatabase(): void
    {
        $registration = $this->registration();

        if (! $registration) {
            return;
        }

        if (LibyanNationalIdSupport::isValid($this->nationalId)) {
            $this->gender = LibyanNationalIdSupport::gender($this->nationalId)->value;
        }

        $this->beneficiariesCount = (string) count($this->beneficiaries);

        $registration->update([
            'current_step' => $this->step,
            'full_name' => $this->verifiedFullName ?: $registration->full_name,
            'national_id' => $this->nationalId ?: $registration->national_id,
            'employee_number' => $this->employeeNumber ?: $registration->employee_number,
            'date_of_birth' => $this->dateOfBirth ?: null,
            'workplace' => $this->workplace ?: null,
            'job_title' => $this->jobTitle ?: null,
            'gender' => $this->gender ?: null,
            'marital_status' => $this->maritalStatus ?: null,
            'beneficiaries_count' => (int) $this->beneficiariesCount,
            'phone' => $this->phone ?: null,
            'whatsapp' => $this->whatsapp ?: null,
            'email' => $this->email ?: null,
            'city' => $this->city ?: null,
            'address' => $this->address ?: null,
            'has_chronic_conditions' => $this->hasChronicConditions,
            'chronic_conditions' => $this->hasChronicConditions ? $this->chronicConditions : null,
            'has_tumor' => $this->hasTumor,
            'has_surgery_history' => $this->hasSurgeryHistory,
            'uses_medical_devices' => $this->usesMedicalDevices,
            'hospitalized_recently' => $this->hospitalizedRecently,
            'traveled_for_treatment' => $this->traveledForTreatment,
        ]);

        $this->hasSavedDraft = true;
    }

    protected function registration(): ?MedicalRegistration
    {
        $sessionId = session('registration_id');

        if (! is_numeric($sessionId)) {
            return null;
        }

        $registration = MedicalRegistration::query()->find((int) $sessionId);

        if ($registration === null) {
            session()->forget('registration_id');

            return null;
        }

        if ($this->registrationId !== $registration->id) {
            $this->registrationId = $registration->id;
        }

        return $registration;
    }

    public function beneficiaryPhotoUrl(?array $beneficiary): ?string
    {
        $registration = $this->registration();

        if (! $registration || blank($beneficiary['photo_path'] ?? null) || blank($beneficiary['id'] ?? null)) {
            return null;
        }

        $model = $registration->beneficiaries->firstWhere('id', $beneficiary['id']);

        return $model ? RegistrationDocuments::beneficiaryUrl($registration, $model) : null;
    }

    protected function syncBeneficiariesToDatabase(): void
    {
        $registration = $this->registration();

        if (! $registration) {
            return;
        }

        $registration->beneficiaries()->delete();

        foreach ($this->beneficiaries as $beneficiary) {
            Beneficiary::query()->create([
                'medical_registration_id' => $registration->id,
                'full_name' => $beneficiary['full_name'],
                'relationship' => $beneficiary['relationship'],
                'is_libyan' => (bool) ($beneficiary['is_libyan'] ?? true),
                'nationality' => $beneficiary['nationality'] ?? null,
                'national_id' => $beneficiary['national_id'] ?? null,
                'passport_number' => $beneficiary['passport_number'] ?? null,
                'date_of_birth' => $beneficiary['date_of_birth'] ?: null,
                'blood_type' => $beneficiary['blood_type'],
                'has_chronic_condition' => (bool) ($beneficiary['has_chronic_conditions'] ?? $beneficiary['has_chronic_condition'] ?? false),
                'has_chronic_conditions' => (bool) ($beneficiary['has_chronic_conditions'] ?? false),
                'chronic_conditions' => $beneficiary['chronic_conditions'] ?? null,
                'has_tumor' => (bool) ($beneficiary['has_tumor'] ?? false),
                'has_surgery_history' => (bool) ($beneficiary['has_surgery_history'] ?? false),
                'uses_medical_devices' => (bool) ($beneficiary['uses_medical_devices'] ?? false),
                'hospitalized_recently' => (bool) ($beneficiary['hospitalized_recently'] ?? false),
                'traveled_for_treatment' => (bool) ($beneficiary['traveled_for_treatment'] ?? false),
                'photo_path' => $beneficiary['photo_path'] ?? null,
            ]);
        }

        $registration->unsetRelation('beneficiaries');
        $this->beneficiaries = $registration->beneficiaries()->get()->map(
            fn (Beneficiary $b) => $this->beneficiaryToArray($b),
        )->all();

        $this->syncFamilyBeneficiariesCount($registration);
        $registration->update(['current_step' => $this->step]);
        $this->hasSavedDraft = true;
    }

    protected function syncFamilyBeneficiariesCount(?MedicalRegistration $registration = null): void
    {
        $count = count($this->beneficiaries);
        $this->beneficiariesCount = (string) $count;

        $registration ??= $this->registration();

        if ($registration && (int) $registration->beneficiaries_count !== $count) {
            $registration->update(['beneficiaries_count' => $count]);
        }
    }

    protected function loadRegistration(MedicalRegistration $registration): void
    {
        $registration->loadMissing(['beneficiaries', 'employee']);
        $this->registrationId = $registration->id;
        session([
            'registration_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        $this->employeeNumber = $registration->employee_number;
        $this->nationalId = $registration->national_id;
        $this->dateOfBirth = $registration->date_of_birth?->format('Y-m-d') ?? '';
        $this->consent = (bool) $registration->consent_at;
        $this->fullName = $registration->full_name;
        $this->verifiedFullName = $registration->full_name;
        $this->workplace = $registration->workplace ?? '';
        $this->office = $registration->employee?->officeLabel() ?? '';
        $this->jobTitle = $registration->job_title ?? 'employee';
        $this->gender = $registration->gender?->value ?? 'male';
        $this->maritalStatus = $registration->marital_status?->value ?? 'married';
        $this->phone = $registration->phone ?? '';
        $this->whatsapp = $registration->whatsapp ?? '';
        $this->email = $registration->email ?? '';
        $this->city = $registration->city ?? '';
        $this->address = $registration->address ?? '';
        $this->hasChronicConditions = (bool) $registration->has_chronic_conditions;
        $this->chronicConditions = $registration->chronic_conditions ?? [];
        $this->hasTumor = (bool) $registration->has_tumor;
        $this->hasSurgeryHistory = (bool) $registration->has_surgery_history;
        $this->usesMedicalDevices = (bool) $registration->uses_medical_devices;
        $this->hospitalizedRecently = (bool) $registration->hospitalized_recently;
        $this->traveledForTreatment = (bool) $registration->traveled_for_treatment;
        $this->referenceNumber = $registration->reference_number ?? '';

        $this->beneficiaries = $registration->beneficiaries->map(
            fn (Beneficiary $b) => $this->beneficiaryToArray($b),
        )->all();

        $this->syncFamilyBeneficiariesCount($registration);

        $this->hasFamilyDocument = (bool) $registration->family_status_document_path;
        $this->hasEmployeePhoto = (bool) $registration->employee_photo_path;

        $resumeStep = (int) ($registration->current_step ?: 0);

        if ($resumeStep < 2) {
            $resumeStep = $this->determineResumeStep($registration);
        }

        // Once identity is verified, never resume on the login gate (step 1).
        $this->step = max(2, $resumeStep);
        $this->identityLocked = true;
    }

    protected function determineResumeStep(MedicalRegistration $registration): int
    {
        if ($registration->hasDocuments()) {
            return 6;
        }

        if ($registration->beneficiaries()->exists()) {
            return 5;
        }

        if ($registration->workplace && $registration->date_of_birth) {
            return 3;
        }

        return 2;
    }

    protected function resetFormState(): void
    {
        $this->reset([
            'step', 'registrationId', 'fullName', 'employeeNumber', 'nationalId', 'dateOfBirth', 'consent',
            'verifiedFullName', 'workplace', 'office', 'jobTitle', 'gender', 'maritalStatus',
            'beneficiariesCount', 'phone', 'whatsapp', 'email', 'city', 'address',
            'hasChronicConditions', 'chronicConditions', 'hasTumor', 'hasSurgeryHistory',
            'usesMedicalDevices', 'hospitalizedRecently', 'traveledForTreatment',
            'beneficiaries', 'showBeneficiaryForm', 'beneficiaryName', 'beneficiaryRelationship',
            'beneficiaryIsLibyan', 'beneficiaryNationality', 'beneficiaryNationalId', 'beneficiaryPassportNumber',
            'beneficiaryDateOfBirth', 'beneficiaryBloodType',
            'beneficiaryHasChronicConditions', 'beneficiaryChronicConditions', 'beneficiaryHasTumor',
            'beneficiaryHasSurgeryHistory', 'beneficiaryUsesMedicalDevices',
            'beneficiaryHospitalizedRecently', 'beneficiaryTraveledForTreatment',
            'beneficiaryPhoto', 'beneficiaryExistingPhotoPath', 'editingBeneficiaryIndex',
            'familyStatusDocument', 'employeePhoto', 'familyStatusDocumentName', 'employeePhotoName',
            'submitted', 'referenceNumber',
            'hasFamilyDocument', 'hasEmployeePhoto', 'hasSavedDraft', 'identityLocked',
            'approvedLocked', 'approvedMessage',
        ]);

        $this->step = 1;
        $this->jobTitle = 'employee';
        $this->gender = 'male';
        $this->maritalStatus = 'married';
        $this->beneficiaryRelationship = BeneficiaryRelationship::Spouse->value;
        $this->beneficiaryIsLibyan = true;
        $this->beneficiaryBloodType = 'a_positive';
    }

    protected function resetBeneficiaryForm(): void
    {
        $this->editingBeneficiaryIndex = null;
        $this->beneficiaryName = '';
        $this->beneficiaryRelationship = $this->defaultBeneficiaryRelationship();
        $this->beneficiaryIsLibyan = true;
        $this->beneficiaryNationality = '';
        $this->beneficiaryNationalId = '';
        $this->beneficiaryPassportNumber = '';
        $this->beneficiaryDateOfBirth = '';
        $this->beneficiaryBloodType = 'a_positive';
        $this->beneficiaryHasChronicConditions = false;
        $this->beneficiaryChronicConditions = [];
        $this->beneficiaryHasTumor = false;
        $this->beneficiaryHasSurgeryHistory = false;
        $this->beneficiaryUsesMedicalDevices = false;
        $this->beneficiaryHospitalizedRecently = false;
        $this->beneficiaryTraveledForTreatment = false;
        $this->beneficiaryPhoto = null;
        $this->beneficiaryExistingPhotoPath = null;
        $this->resetValidation([
            'beneficiaryName',
            'beneficiaryRelationship',
            'beneficiaryIsLibyan',
            'beneficiaryNationality',
            'beneficiaryNationalId',
            'beneficiaryPassportNumber',
            'beneficiaryDateOfBirth',
            'beneficiaryBloodType',
            'beneficiaryChronicConditions',
            'beneficiaryPhoto',
        ]);
    }

    protected function syncBeneficiaryCitizenshipToRelationship(): void
    {
        if ($this->currentBeneficiaryMustBeNonLibyan()) {
            $this->beneficiaryIsLibyan = false;
        } elseif (! $this->beneficiaryRelationshipAllowsNonLibyan()) {
            $this->beneficiaryIsLibyan = true;
            $this->beneficiaryNationality = '';
            $this->beneficiaryPassportNumber = '';
        }

        $this->syncBeneficiaryIdentityFieldsToCitizenship();
    }

    protected function syncBeneficiaryIdentityFieldsToCitizenship(): void
    {
        if ($this->beneficiaryIsLibyanForCurrentRelationship()) {
            $this->beneficiaryNationality = '';
            $this->beneficiaryPassportNumber = '';
        } else {
            $this->beneficiaryNationalId = '';
        }
    }

    protected function beneficiaryRelationshipAllowsNonLibyan(): bool
    {
        $relationship = BeneficiaryRelationship::tryFrom($this->beneficiaryRelationship);

        if ($relationship?->allowsNonLibyan()) {
            return true;
        }

        return $this->currentBeneficiaryMustBeNonLibyan();
    }

    protected function beneficiaryIsLibyanForCurrentRelationship(): bool
    {
        if ($this->currentBeneficiaryMustBeNonLibyan()) {
            return false;
        }

        if (! $this->beneficiaryRelationshipAllowsNonLibyan()) {
            return true;
        }

        return $this->beneficiaryIsLibyan;
    }

    /**
     * Female employee with a non-Libyan husband → children cannot be Libyan.
     */
    protected function childrenMustBeNonLibyan(): bool
    {
        return $this->hasNonLibyanHusband();
    }

    protected function currentBeneficiaryMustBeNonLibyan(): bool
    {
        $relationship = BeneficiaryRelationship::tryFrom($this->beneficiaryRelationship);

        return $relationship !== null
            && $relationship->isChild()
            && $this->childrenMustBeNonLibyan();
    }

    protected function hasNonLibyanHusband(): bool
    {
        if ($this->employeeGender() !== Gender::Female) {
            return false;
        }

        return collect($this->beneficiaries)->contains(function (array $beneficiary, int $index): bool {
            if ($this->editingBeneficiaryIndex === $index) {
                return $this->beneficiaryRelationship === BeneficiaryRelationship::Spouse->value
                    && ! $this->beneficiaryIsLibyan;
            }

            return ($beneficiary['relationship'] ?? null) === BeneficiaryRelationship::Spouse->value
                && ! (bool) ($beneficiary['is_libyan'] ?? true);
        });
    }

    protected function hasLibyanChildren(?int $exceptIndex = null): bool
    {
        return collect($this->beneficiaries)->contains(function (array $beneficiary, int $index) use ($exceptIndex): bool {
            if ($exceptIndex !== null && $index === $exceptIndex) {
                return false;
            }

            $relationship = BeneficiaryRelationship::tryFrom($beneficiary['relationship'] ?? '');

            return $relationship?->isChild() === true
                && (bool) ($beneficiary['is_libyan'] ?? true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function beneficiaryToArray(Beneficiary $beneficiary): array
    {
        return [
            'id' => $beneficiary->id,
            'full_name' => $beneficiary->full_name,
            'relationship' => $beneficiary->relationship->value,
            'is_libyan' => (bool) $beneficiary->is_libyan,
            'nationality' => $beneficiary->nationality,
            'national_id' => $beneficiary->national_id,
            'passport_number' => $beneficiary->passport_number,
            'date_of_birth' => $beneficiary->date_of_birth?->format('Y-m-d'),
            'blood_type' => $beneficiary->blood_type?->value,
            'has_chronic_condition' => $beneficiary->has_chronic_condition || $beneficiary->has_chronic_conditions,
            'has_chronic_conditions' => $beneficiary->has_chronic_conditions || $beneficiary->has_chronic_condition,
            'chronic_conditions' => $beneficiary->chronic_conditions ?? [],
            'has_tumor' => $beneficiary->has_tumor,
            'has_surgery_history' => $beneficiary->has_surgery_history,
            'uses_medical_devices' => $beneficiary->uses_medical_devices,
            'hospitalized_recently' => $beneficiary->hospitalized_recently,
            'traveled_for_treatment' => $beneficiary->traveled_for_treatment,
            'photo_path' => $beneficiary->photo_path,
        ];
    }

    public function beneficiaryIdentityLabel(array $beneficiary): string
    {
        $isLibyan = (bool) ($beneficiary['is_libyan'] ?? true);

        if ($isLibyan) {
            return filled($beneficiary['national_id'] ?? null)
                ? (string) $beneficiary['national_id']
                : '—';
        }

        $nationality = filled($beneficiary['nationality'] ?? null)
            ? (config('registration.nationalities.'.$beneficiary['nationality']) ?? $beneficiary['nationality'])
            : null;
        $passport = filled($beneficiary['passport_number'] ?? null)
            ? 'جواز: '.$beneficiary['passport_number']
            : null;

        $parts = array_filter([$nationality, $passport]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    protected function syncBeneficiaryRelationshipToMaritalStatus(): void
    {
        $allowed = array_map(
            fn (BeneficiaryRelationship $relationship): string => $relationship->value,
            $this->availableBeneficiaryRelationships(),
        );

        if (! in_array($this->beneficiaryRelationship, $allowed, true)) {
            $this->beneficiaryRelationship = $this->defaultBeneficiaryRelationship();
        }
    }

    protected function defaultBeneficiaryRelationship(): string
    {
        $available = $this->availableBeneficiaryRelationships();

        return ($available[0] ?? BeneficiaryRelationship::Father)->value;
    }

    protected function employeeGender(): Gender
    {
        return Gender::tryFrom($this->gender) ?? Gender::Male;
    }

    /**
     * Neighbors and common nationalities first, then A–Z, with "أخرى" last.
     *
     * @return array<string, string>
     */
    protected function orderedNationalities(): array
    {
        $nationalities = config('registration.nationalities', []);
        $priorityRank = array_flip(array_values(array_unique(config('registration.nationality_priority', []))));

        return collect($nationalities)
            ->sortBy(function (string $label, string $key) use ($priorityRank): array {
                if ($key === 'other') {
                    return [2, $label];
                }

                if (isset($priorityRank[$key])) {
                    return [0, sprintf('%03d', $priorityRank[$key])];
                }

                return [1, $label];
            })
            ->all();
    }

    /**
     * @return list<BeneficiaryRelationship>
     */
    protected function availableBeneficiaryRelationships(): array
    {
        $available = BeneficiaryRelationship::availableFor($this->maritalStatus);
        $exceptIndex = $this->editingBeneficiaryIndex;
        $spouseCount = $this->spouseCount($exceptIndex);
        $maxSpouses = BeneficiaryRelationship::maxSpousesFor($this->employeeGender());

        $editingIsSpouse = $exceptIndex !== null
            && ($this->beneficiaries[$exceptIndex]['relationship'] ?? null) === BeneficiaryRelationship::Spouse->value;

        if ($spouseCount >= $maxSpouses && ! $editingIsSpouse) {
            return array_values(array_filter(
                $available,
                fn (BeneficiaryRelationship $relationship): bool => $relationship !== BeneficiaryRelationship::Spouse,
            ));
        }

        return $available;
    }

    protected function spouseCount(?int $exceptIndex = null): int
    {
        return collect($this->beneficiaries)
            ->filter(function (array $beneficiary, int $index) use ($exceptIndex): bool {
                if ($exceptIndex !== null && $index === $exceptIndex) {
                    return false;
                }

                return ($beneficiary['relationship'] ?? null) === BeneficiaryRelationship::Spouse->value;
            })
            ->count();
    }

    protected function spouseLimitMessage(Gender $employeeGender, int $maxSpouses): string
    {
        return match ($employeeGender) {
            Gender::Male => "يمكن إضافة حتى {$maxSpouses} زوجات فقط",
            Gender::Female => 'يمكن إضافة زوج واحد فقط',
        };
    }

    protected function beneficiaryRelationshipValidationMessage(): string
    {
        if ($this->maritalStatus === MaritalStatus::Single->value) {
            return 'الأعزب يمكنه إضافة الوالدين فقط';
        }

        if ($this->beneficiaryRelationship === BeneficiaryRelationship::Spouse->value) {
            return $this->spouseLimitMessage(
                $this->employeeGender(),
                BeneficiaryRelationship::maxSpousesFor($this->employeeGender()),
            );
        }

        return 'صلة القرابة غير صالحة';
    }

    protected function notify(string $message): void
    {
        $this->toastMessage = $message;
    }

    protected function showSubmittedSuccess(MedicalRegistration $registration, bool $notify = false): void
    {
        $this->submitted = true;
        $this->identityLocked = true;
        $this->referenceNumber = $registration->reference_number ?? '';
        $this->registrationId = $registration->id;
        // Keep the UI off the login gate even if current_step was saved as 1.
        $this->step = max(2, (int) ($registration->current_step ?: 6));
        session([
            'registration_id' => $registration->id,
            'reference_download_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        session()->forget('registration_editing');

        if ($notify) {
            $this->notify('طلبك مُرسَل مسبقاً — يمكنك تحميل بطاقة المراجعة أو التعديل');
        }
    }

    protected function resumeEditingSubmittedRegistration(MedicalRegistration $registration): void
    {
        $this->loadRegistration($registration->loadMissing('beneficiaries'));
        $this->submitted = false;
        $this->identityLocked = true;
        $this->hasSavedDraft = true;
        session([
            'registration_id' => $registration->id,
            'registration_editing' => true,
            'registration_gate_passed' => true,
        ]);
    }

    protected function isFormLocked(): bool
    {
        return $this->submitted || $this->approvedLocked;
    }

    /**
     * Livewire throws MissingRulesException when validate() receives an empty rules array
     * (e.g. documents already on file and no new uploads). Skip safely in that case.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    protected function validateRules(array $rules, array $messages = []): void
    {
        if ($rules === []) {
            return;
        }

        try {
            $this->validate($rules, $messages);
        } catch (ValidationException $exception) {
            $this->dispatchScrollToError(array_key_first($exception->errors()));

            throw $exception;
        }
    }

    /**
     * @param  array<string, string|list<string>>  $messages
     */
    protected function failValidation(array $messages): never
    {
        $this->dispatchScrollToError(array_key_first($messages));

        throw ValidationException::withMessages($messages);
    }

    protected function addVisibleError(string $field, string $message): void
    {
        $this->addError($field, $message);
        $this->dispatchScrollToError($field);
    }

    protected function dispatchScrollToError(?string $field): void
    {
        $name = $field ? explode('.', $field)[0] : null;

        $this->dispatch('reg-scroll-to-error', field: $name);
        $this->js('window.regScrollToValidationError('.json_encode($name).')');
    }

    protected function syncDirectoryFromEmployee(Employee $employee): void
    {
        if (blank($this->workplace) && filled($employee->workplace)) {
            $this->workplace = $employee->workplace;
        }

        $this->office = $employee->officeLabel() ?? '';
    }

    /**
     * @param  'familyStatusDocument'|'employeePhoto'  $property
     */
    protected function storeUploadedDocument(string $property): void
    {
        if ($this->isFormLocked() || ! $this->{$property} instanceof TemporaryUploadedFile) {
            return;
        }

        $isFamily = $property === 'familyStatusDocument';

        try {
            $this->validateOnly($property, [
                $property => $isFamily
                    ? RegistrationDocuments::familyValidationRules()
                    : RegistrationDocuments::photoValidationRules(),
            ], $this->documentValidationMessages());
        } catch (ValidationException $exception) {
            $this->dispatchScrollToError($property);

            throw $exception;
        }

        $registration = $this->registration();

        if (! $registration) {
            return;
        }

        if ($isFamily) {
            $this->persistFamilyStatusDocument($registration);
        } else {
            $this->persistEmployeePhoto($registration);
        }
    }

    protected function persistFamilyStatusDocument(MedicalRegistration $registration): void
    {
        if (! $this->familyStatusDocument instanceof TemporaryUploadedFile) {
            return;
        }

        $previous = $registration->family_status_document_path;
        $path = $this->familyStatusDocument->store(
            "registrations/{$registration->uuid}",
            RegistrationDocuments::diskName(),
        );

        $this->familyStatusDocumentName = $this->familyStatusDocument->getClientOriginalName();
        $registration->family_status_document_path = $path;
        $registration->save();

        if (filled($previous) && $previous !== $path) {
            RegistrationDocuments::disk()->delete($previous);
        }

        $this->hasFamilyDocument = true;
        $this->familyStatusDocument = null;
    }

    protected function persistEmployeePhoto(MedicalRegistration $registration): void
    {
        if (! $this->employeePhoto instanceof TemporaryUploadedFile) {
            return;
        }

        $previous = $registration->employee_photo_path;
        $path = $this->employeePhoto->store(
            "registrations/{$registration->uuid}",
            RegistrationDocuments::diskName(),
        );

        $this->employeePhotoName = $this->employeePhoto->getClientOriginalName();
        $registration->employee_photo_path = $path;
        $registration->save();

        if (filled($previous) && $previous !== $path) {
            RegistrationDocuments::disk()->delete($previous);
        }

        $this->hasEmployeePhoto = true;
        $this->employeePhoto = null;
    }

    /**
     * @return array<string, string>
     */
    protected function documentValidationMessages(): array
    {
        $familyMaxMb = RegistrationDocuments::maxMegabytes();
        $photoMaxMb = RegistrationDocuments::photoMaxMegabytes();

        return [
            'familyStatusDocument.required' => 'صورة من شهادة الوضع العائلي مطلوبة',
            'familyStatusDocument.mimes' => 'يجب أن تكون شهادة الوضع العائلي بصيغة PDF أو JPG أو PNG أو WEBP',
            'familyStatusDocument.max' => "حجم شهادة الوضع العائلي يجب ألا يتجاوز {$familyMaxMb} م.ب",
            'employeePhoto.required' => 'الصورة الشخصية للموظف مطلوبة',
            'employeePhoto.mimes' => 'يجب أن تكون صورة الموظف بصيغة JPG أو PNG',
            'employeePhoto.max' => "حجم صورة الموظف يجب ألا يتجاوز {$photoMaxMb} م.ب",
            'beneficiaryPhoto.mimes' => 'يجب أن تكون صورة المستفيد بصيغة JPG أو PNG',
            'beneficiaryPhoto.max' => "حجم صورة المستفيد يجب ألا يتجاوز {$photoMaxMb} م.ب",
        ];
    }
}
