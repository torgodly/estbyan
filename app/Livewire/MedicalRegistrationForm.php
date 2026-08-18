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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
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

    public ?int $registrationId = null;

    public string $employeeNumber = '';

    public string $nationalId = '';

    public string $dateOfBirth = '';

    public bool $consent = false;

    public string $fullName = '';

    public string $verifiedFullName = '';

    public string $workplace = '';

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

    public string $beneficiaryNationalId = '';

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

        if ($this->isStepOneField($property) && ! $this->registrationId) {
            $this->persistStepOneDraft();

            return;
        }

        if ($this->registrationId && $this->isAutoPersistField($property)) {
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
        $this->validateRules([
            'nationalId' => ['required', 'string', new LibyanNationalId],
            'consent' => ['accepted'],
        ], [
            'nationalId.required' => 'الرقم الوطني مطلوب',
            'consent.accepted' => 'يجب الموافقة على سياسة الخصوصية للمتابعة',
        ]);

        $employee = Employee::findForVerification($this->nationalId);

        if (! $employee) {
            $this->addError('nationalId', 'لم يتم العثور على موظف بهذا الرقم الوطني.');

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
                'workplace' => $employee->workplace,
                'gender' => $genderFromNid,
                'consent_at' => $existing->consent_at ?? now(),
            ]);

            $existing = $existing->fresh('beneficiaries');
            $this->loadRegistration($existing);
            $this->identityLocked = true;
            $this->gender = $genderFromNid;
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
            'beneficiariesCount' => ['required', 'integer', 'min:0', 'max:20'],
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

        $rules = [
            'beneficiaryName' => ['required', 'string', 'max:255'],
            'beneficiaryRelationship' => [
                'required',
                Rule::in(array_map(
                    fn (BeneficiaryRelationship $r) => $r->value,
                    BeneficiaryRelationship::availableFor($this->maritalStatus),
                )),
            ],
            'beneficiaryNationalId' => ['required', 'string', new LibyanNationalId],
            'beneficiaryDateOfBirth' => [
                'required',
                'date',
                'before:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! LibyanNationalIdSupport::matchesDateOfBirth($this->beneficiaryNationalId, $value)) {
                        $fail('سنة ميلاد المستفيد يجب أن تطابق السنة في رقمه الوطني.');
                    }
                },
            ],
            'beneficiaryBloodType' => ['required', Rule::in(array_map(fn (BloodType $b) => $b->value, BloodType::cases()))],
            'beneficiaryChronicConditions' => [Rule::requiredIf($this->beneficiaryHasChronicConditions), 'array'],
            'beneficiaryPhoto' => [
                Rule::requiredIf($this->editingBeneficiaryIndex === null && blank($this->beneficiaryExistingPhotoPath)),
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:10240',
            ],
        ];

        $this->validateRules($rules, [
            'beneficiaryName.required' => 'اسم المستفيد مطلوب',
            'beneficiaryRelationship.in' => $this->maritalStatus === MaritalStatus::Single->value
                ? 'الأعزب يمكنه إضافة الوالدين فقط'
                : 'صلة القرابة غير صالحة',
            'beneficiaryNationalId.required' => 'الرقم الوطني للمستفيد مطلوب',
            'beneficiaryDateOfBirth.required' => 'تاريخ ميلاد المستفيد مطلوب',
            'beneficiaryPhoto.required' => 'صورة المستفيد مطلوبة',
            'beneficiaryChronicConditions.required' => 'يرجى تحديد الأمراض المزمنة على الأقل',
        ]);

        $relationship = BeneficiaryRelationship::from($this->beneficiaryRelationship);
        $expectedGender = $relationship->expectedGender();

        if (
            $expectedGender !== null
            && LibyanNationalIdSupport::isValid($this->beneficiaryNationalId)
            && ! LibyanNationalIdSupport::matchesGender($this->beneficiaryNationalId, $expectedGender)
        ) {
            $digit = $expectedGender === Gender::Male ? '1' : '2';
            $genderLabel = $expectedGender === Gender::Male ? 'ذكر' : 'أنثى';

            throw ValidationException::withMessages([
                'beneficiaryNationalId' => "الرقم الوطني لـ{$relationship->label()} يجب أن يبدأ بـ {$digit} ({$genderLabel}).",
            ]);
        }

        $registration = $this->registration();

        if (! $registration) {
            return;
        }

        $photoPath = $this->beneficiaryExistingPhotoPath;

        if ($this->beneficiaryPhoto instanceof TemporaryUploadedFile) {
            $photoPath = $this->beneficiaryPhoto->store(
                "registrations/{$registration->uuid}/beneficiaries",
                RegistrationDocuments::diskName(),
            );
        }

        if (blank($photoPath)) {
            throw ValidationException::withMessages([
                'beneficiaryPhoto' => 'صورة المستفيد مطلوبة',
            ]);
        }

        $data = [
            'full_name' => $this->beneficiaryName,
            'relationship' => $this->beneficiaryRelationship,
            'national_id' => $this->beneficiaryNationalId,
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
        $this->beneficiaryNationalId = $beneficiary['national_id'] ?? '';
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
            $this->addError(
                'beneficiaries',
                $this->maritalStatus === MaritalStatus::Single->value
                    ? 'الحالة أعزب — يرجى حذف المستفيدين من غير الوالدين قبل المتابعة'
                    : 'يوجد مستفيدون بصلة قرابة غير صالحة',
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
            return;
        }

        $rules = [];

        if ($this->familyStatusDocument !== null || blank($registration->family_status_document_path)) {
            $rules['familyStatusDocument'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        }

        if ($this->employeePhoto !== null || blank($registration->employee_photo_path)) {
            $rules['employeePhoto'] = ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'];
        }

        $this->validateRules($rules, [
            'familyStatusDocument.required' => 'صورة من شهادة الوضع العائلي مطلوبة',
            'familyStatusDocument.mimes' => 'يجب أن تكون شهادة الوضع العائلي بصيغة PDF أو JPG أو PNG',
            'employeePhoto.required' => 'الصورة الشخصية للموظف مطلوبة',
            'employeePhoto.mimes' => 'يجب أن تكون صورة الموظف بصيغة JPG أو PNG',
        ]);

        $path = "registrations/{$registration->uuid}";

        if ($this->familyStatusDocument) {
            $registration->family_status_document_path = $this->familyStatusDocument->store(
                $path,
                RegistrationDocuments::diskName(),
            );
        }

        if ($this->employeePhoto) {
            $registration->employee_photo_path = $this->employeePhoto->store(
                $path,
                RegistrationDocuments::diskName(),
            );
        }

        if (blank($registration->family_status_document_path)) {
            $this->addError('familyStatusDocument', 'صورة من شهادة الوضع العائلي مطلوبة');

            return;
        }

        if (blank($registration->employee_photo_path)) {
            $this->addError('employeePhoto', 'الصورة الشخصية للموظف مطلوبة');

            return;
        }

        $registration->save();
        $this->hasFamilyDocument = true;
        $this->hasEmployeePhoto = true;
        $this->goToStep(6);
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        if ($this->registrationId) {
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
            $this->addError('submit', 'يرجى إرفاق صورة من شهادة الوضع العائلي والصورة الشخصية قبل الإرسال');

            return;
        }

        $missingBeneficiaryPhoto = collect($this->beneficiaries)->contains(
            fn (array $beneficiary): bool => blank($beneficiary['photo_path'] ?? null),
        );

        if ($missingBeneficiaryPhoto) {
            $this->addError('submit', 'يجب إرفاق صورة لكل مستفيد قبل الإرسال');

            return;
        }

        if (! $registration->isEditableByEmployee()) {
            $this->addError('submit', 'لا يمكن تعديل طلب معتمد');

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
            'chronicConditionOptions' => config('registration.chronic_conditions'),
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

        if ($this->registrationId) {
            MedicalRegistration::query()
                ->whereKey($this->registrationId)
                ->update(['current_step' => $step]);
        }
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
            'dateOfBirth', 'workplace', 'jobTitle', 'gender', 'maritalStatus', 'beneficiariesCount',
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
            'beneficiaries_count' => $this->beneficiariesCount !== '' ? (int) $this->beneficiariesCount : null,
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
        if (! $this->registrationId) {
            return null;
        }

        return MedicalRegistration::query()->find($this->registrationId);
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
                'national_id' => $beneficiary['national_id'] ?? null,
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
        $this->beneficiaries = $registration->beneficiaries()->get()->map(fn (Beneficiary $b) => [
            'id' => $b->id,
            'full_name' => $b->full_name,
            'relationship' => $b->relationship->value,
            'national_id' => $b->national_id,
            'date_of_birth' => $b->date_of_birth?->format('Y-m-d'),
            'blood_type' => $b->blood_type?->value,
            'has_chronic_condition' => $b->has_chronic_condition || $b->has_chronic_conditions,
            'has_chronic_conditions' => $b->has_chronic_conditions || $b->has_chronic_condition,
            'chronic_conditions' => $b->chronic_conditions ?? [],
            'has_tumor' => $b->has_tumor,
            'has_surgery_history' => $b->has_surgery_history,
            'uses_medical_devices' => $b->uses_medical_devices,
            'hospitalized_recently' => $b->hospitalized_recently,
            'traveled_for_treatment' => $b->traveled_for_treatment,
            'photo_path' => $b->photo_path,
        ])->all();

        $registration->update(['current_step' => $this->step]);
        $this->hasSavedDraft = true;
    }

    protected function loadRegistration(MedicalRegistration $registration): void
    {
        $this->registrationId = $registration->id;
        $this->employeeNumber = $registration->employee_number;
        $this->nationalId = $registration->national_id;
        $this->dateOfBirth = $registration->date_of_birth?->format('Y-m-d') ?? '';
        $this->consent = (bool) $registration->consent_at;
        $this->fullName = $registration->full_name;
        $this->verifiedFullName = $registration->full_name;
        $this->workplace = $registration->workplace ?? '';
        $this->jobTitle = $registration->job_title ?? 'employee';
        $this->gender = $registration->gender?->value ?? 'male';
        $this->maritalStatus = $registration->marital_status?->value ?? 'married';
        $this->beneficiariesCount = (string) ($registration->beneficiaries_count ?? '');
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

        $this->beneficiaries = $registration->beneficiaries->map(fn (Beneficiary $b) => [
            'id' => $b->id,
            'full_name' => $b->full_name,
            'relationship' => $b->relationship->value,
            'national_id' => $b->national_id,
            'date_of_birth' => $b->date_of_birth?->format('Y-m-d'),
            'blood_type' => $b->blood_type?->value,
            'has_chronic_condition' => $b->has_chronic_condition || $b->has_chronic_conditions,
            'has_chronic_conditions' => $b->has_chronic_conditions || $b->has_chronic_condition,
            'chronic_conditions' => $b->chronic_conditions ?? [],
            'has_tumor' => $b->has_tumor,
            'has_surgery_history' => $b->has_surgery_history,
            'uses_medical_devices' => $b->uses_medical_devices,
            'hospitalized_recently' => $b->hospitalized_recently,
            'traveled_for_treatment' => $b->traveled_for_treatment,
            'photo_path' => $b->photo_path,
        ])->all();

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
            'verifiedFullName', 'workplace', 'jobTitle', 'gender', 'maritalStatus',
            'beneficiariesCount', 'phone', 'whatsapp', 'email', 'city', 'address',
            'hasChronicConditions', 'chronicConditions', 'hasTumor', 'hasSurgeryHistory',
            'usesMedicalDevices', 'hospitalizedRecently', 'traveledForTreatment',
            'beneficiaries', 'showBeneficiaryForm', 'beneficiaryName', 'beneficiaryRelationship',
            'beneficiaryNationalId', 'beneficiaryDateOfBirth', 'beneficiaryBloodType',
            'beneficiaryHasChronicConditions', 'beneficiaryChronicConditions', 'beneficiaryHasTumor',
            'beneficiaryHasSurgeryHistory', 'beneficiaryUsesMedicalDevices',
            'beneficiaryHospitalizedRecently', 'beneficiaryTraveledForTreatment',
            'beneficiaryPhoto', 'beneficiaryExistingPhotoPath', 'editingBeneficiaryIndex',
            'familyStatusDocument', 'employeePhoto', 'submitted', 'referenceNumber',
            'hasFamilyDocument', 'hasEmployeePhoto', 'hasSavedDraft', 'identityLocked',
            'approvedLocked', 'approvedMessage',
        ]);

        $this->step = 1;
        $this->jobTitle = 'employee';
        $this->gender = 'male';
        $this->maritalStatus = 'married';
        $this->beneficiaryRelationship = BeneficiaryRelationship::Spouse->value;
        $this->beneficiaryBloodType = 'a_positive';
    }

    protected function resetBeneficiaryForm(): void
    {
        $this->editingBeneficiaryIndex = null;
        $this->beneficiaryName = '';
        $this->beneficiaryRelationship = $this->defaultBeneficiaryRelationship();
        $this->beneficiaryNationalId = '';
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
            'beneficiaryNationalId',
            'beneficiaryDateOfBirth',
            'beneficiaryBloodType',
            'beneficiaryChronicConditions',
            'beneficiaryPhoto',
        ]);
    }

    protected function syncBeneficiaryRelationshipToMaritalStatus(): void
    {
        $allowed = array_map(
            fn (BeneficiaryRelationship $relationship): string => $relationship->value,
            BeneficiaryRelationship::availableFor($this->maritalStatus),
        );

        if (! in_array($this->beneficiaryRelationship, $allowed, true)) {
            $this->beneficiaryRelationship = $this->defaultBeneficiaryRelationship();
        }
    }

    protected function defaultBeneficiaryRelationship(): string
    {
        $available = BeneficiaryRelationship::availableFor($this->maritalStatus);

        return ($available[0] ?? BeneficiaryRelationship::Father)->value;
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

        $this->validate($rules, $messages);
    }
}
