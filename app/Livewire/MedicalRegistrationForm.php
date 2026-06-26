<?php

namespace App\Livewire;

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\RegistrationStatus;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.registration')]
#[Title('التسجيل الطبي للموظفين — SMART CARE')]
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

    public bool $beneficiaryHasChronic = false;

    public ?int $editingBeneficiaryIndex = null;

    public $familyStatusDocument = null;

    public $employeePhoto = null;

    public bool $submitted = false;

    public string $referenceNumber = '';

    public bool $hasFamilyDocument = false;

    public bool $hasEmployeePhoto = false;

    public ?string $toastMessage = null;

    public bool $hasSavedDraft = false;

    public function mount(): void
    {
        $this->restoreFromSession();
    }

    public function updated(mixed $property): void
    {
        if ($this->submitted) {
            return;
        }

        if ($property === 'hasChronicConditions' && ! $this->hasChronicConditions) {
            $this->chronicConditions = [];
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

        if ($registration) {
            if ($registration->family_status_document_path) {
                Storage::disk('public')->delete($registration->family_status_document_path);
            }

            if ($registration->employee_photo_path) {
                Storage::disk('public')->delete($registration->employee_photo_path);
            }

            Storage::disk('public')->deleteDirectory("registrations/{$registration->uuid}");
            $registration->beneficiaries()->delete();
            $registration->delete();
        }

        session()->forget(['registration_id', 'registration_step1']);

        $this->resetFormState();
        $this->toastMessage = 'تم مسح جميع البيانات. يمكنك البدء من جديد.';
    }

    public function verifyIdentity(): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'min:3', 'max:255'],
            'employeeNumber' => ['required', 'string', 'max:20'],
            'nationalId' => ['required', 'string', 'digits_between:10,15'],
            'dateOfBirth' => ['required', 'date', 'before:today'],
            'consent' => ['accepted'],
        ], [
            'fullName.required' => 'الاسم الكامل مطلوب',
            'fullName.min' => 'الاسم الكامل قصير جداً',
            'employeeNumber.required' => 'الرقم الوظيفي مطلوب',
            'nationalId.required' => 'الرقم الوطني مطلوب',
            'dateOfBirth.required' => 'تاريخ الميلاد مطلوب',
            'consent.accepted' => 'يجب الموافقة على سياسة الخصوصية للمتابعة',
        ]);

        $this->verifiedFullName = trim($this->fullName);

        $registration = MedicalRegistration::query()->updateOrCreate(
            [
                'employee_number' => $this->employeeNumber,
                'national_id' => $this->nationalId,
                'status' => RegistrationStatus::Draft,
            ],
            [
                'employee_id' => null,
                'date_of_birth' => $this->dateOfBirth,
                'full_name' => $this->verifiedFullName,
                'consent_at' => now(),
            ],
        );

        $this->registrationId = $registration->id;
        session(['registration_id' => $registration->id]);
        session()->forget('registration_step1');
        $this->goToStep(2);
        $this->notify('تم حفظ بياناتك — تابع إكمال التسجيل');
    }

    public function saveEmployeeDetails(): void
    {
        $this->validate([
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
        $this->validate([
            'chronicConditions' => [Rule::requiredIf($this->hasChronicConditions), 'array'],
        ], [
            'chronicConditions.required' => 'يرجى تحديد الأمراض المزمنة على الأقل',
        ]);

        $this->autoPersistToDatabase();
        $this->goToStep(4);
    }

    public function toggleBeneficiaryForm(): void
    {
        $this->showBeneficiaryForm = ! $this->showBeneficiaryForm;
        $this->resetBeneficiaryForm();
    }

    public function saveBeneficiary(): void
    {
        $this->validate([
            'beneficiaryName' => ['required', 'string', 'max:255'],
            'beneficiaryRelationship' => ['required', Rule::in(array_map(fn (BeneficiaryRelationship $r) => $r->value, BeneficiaryRelationship::cases()))],
            'beneficiaryNationalId' => ['nullable', 'string', 'digits_between:10,15'],
            'beneficiaryDateOfBirth' => ['nullable', 'date', 'before:today'],
            'beneficiaryBloodType' => ['required', Rule::in(array_map(fn (BloodType $b) => $b->value, BloodType::cases()))],
        ], [
            'beneficiaryName.required' => 'اسم المستفيد مطلوب',
        ]);

        $data = [
            'full_name' => $this->beneficiaryName,
            'relationship' => $this->beneficiaryRelationship,
            'national_id' => $this->beneficiaryNationalId ?: null,
            'date_of_birth' => $this->beneficiaryDateOfBirth ?: null,
            'blood_type' => $this->beneficiaryBloodType,
            'has_chronic_condition' => $this->beneficiaryHasChronic,
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
        $this->beneficiaryHasChronic = (bool) ($beneficiary['has_chronic_condition'] ?? false);
        $this->showBeneficiaryForm = true;
    }

    public function deleteBeneficiary(int $index): void
    {
        unset($this->beneficiaries[$index]);
        $this->beneficiaries = array_values($this->beneficiaries);
        $this->syncBeneficiariesToDatabase();
    }

    public function continueFromBeneficiaries(): void
    {
        $this->goToStep(5);
    }

    public function saveDocuments(): void
    {
        $rules = [
            'familyStatusDocument' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'employeePhoto' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];

        if ($this->familyStatusDocument === null && $this->registration()?->family_status_document_path) {
            unset($rules['familyStatusDocument']);
        }

        $this->validate($rules, [
            'familyStatusDocument.required' => 'شهادة الوضع العائلي مطلوبة (PDF)',
            'familyStatusDocument.mimes' => 'يجب أن تكون شهادة الوضع العائلي بصيغة PDF',
        ]);

        $registration = $this->registration();

        if (! $registration) {
            return;
        }

        $path = "registrations/{$registration->uuid}";

        if ($this->familyStatusDocument) {
            $familyPath = $this->familyStatusDocument->store($path, 'public');
            $registration->family_status_document_path = $familyPath;
        }

        if ($this->employeePhoto) {
            $photoPath = $this->employeePhoto->store($path, 'public');
            $registration->employee_photo_path = $photoPath;
        }

        $registration->save();
        $this->hasFamilyDocument = (bool) $registration->family_status_document_path;
        $this->hasEmployeePhoto = (bool) $registration->employee_photo_path;
        $this->goToStep(6);
    }

    public function saveDraft(): void
    {
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
        $registration = $this->registration();

        if (! $registration || (! $registration->family_status_document_path && ! $this->hasFamilyDocument)) {
            $this->addError('submit', 'يرجى إرفاق جميع المستندات المطلوبة قبل الإرسال');

            return;
        }

        DB::transaction(function () use ($registration): void {
            $registration->update([
                'status' => RegistrationStatus::Submitted,
                'submitted_at' => now(),
                'reference_number' => MedicalRegistration::generateReferenceNumber(),
            ]);
        });

        session()->forget(['registration_id', 'registration_step1']);
        $this->referenceNumber = $registration->fresh()->reference_number ?? '';
        $this->submitted = true;
    }

    public function goBack(): void
    {
        if ($this->step > 1) {
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
                1 => 'بيانات الهوية',
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

            if ($registration && ! $registration->isSubmitted()) {
                $this->loadRegistration($registration);
                $this->hasSavedDraft = true;

                return;
            }
        }

        if ($draft = session('registration_step1')) {
            $this->fullName = $draft['full_name'] ?? '';
            $this->employeeNumber = $draft['employee_number'] ?? '';
            $this->nationalId = $draft['national_id'] ?? '';
            $this->dateOfBirth = $draft['date_of_birth'] ?? '';
            $this->consent = (bool) ($draft['consent'] ?? false);
            $this->step = 1;
            $this->hasSavedDraft = true;
        }
    }

    protected function persistStepOneDraft(): void
    {
        session([
            'registration_step1' => [
                'full_name' => $this->fullName,
                'employee_number' => $this->employeeNumber,
                'national_id' => $this->nationalId,
                'date_of_birth' => $this->dateOfBirth,
                'consent' => $this->consent,
            ],
        ]);

        $this->hasSavedDraft = true;
    }

    protected function isStepOneField(string $property): bool
    {
        return in_array($property, ['fullName', 'employeeNumber', 'nationalId', 'dateOfBirth', 'consent'], true);
    }

    protected function isAutoPersistField(string $property): bool
    {
        return in_array($property, [
            'workplace', 'jobTitle', 'gender', 'maritalStatus', 'beneficiariesCount',
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

        $registration->update([
            'current_step' => $this->step,
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
                'has_chronic_condition' => (bool) ($beneficiary['has_chronic_condition'] ?? false),
            ]);
        }

        $registration->update(['current_step' => $this->step]);
        $this->hasSavedDraft = true;
    }

    protected function loadRegistration(MedicalRegistration $registration): void
    {
        $this->registrationId = $registration->id;
        $this->employeeNumber = $registration->employee_number;
        $this->nationalId = $registration->national_id;
        $this->dateOfBirth = $registration->date_of_birth->format('Y-m-d');
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
        $this->hasChronicConditions = $registration->has_chronic_conditions;
        $this->chronicConditions = $registration->chronic_conditions ?? [];
        $this->hasTumor = $registration->has_tumor;
        $this->hasSurgeryHistory = $registration->has_surgery_history;
        $this->usesMedicalDevices = $registration->uses_medical_devices;
        $this->hospitalizedRecently = $registration->hospitalized_recently;
        $this->traveledForTreatment = $registration->traveled_for_treatment;

        $this->beneficiaries = $registration->beneficiaries->map(fn (Beneficiary $b) => [
            'full_name' => $b->full_name,
            'relationship' => $b->relationship->value,
            'national_id' => $b->national_id,
            'date_of_birth' => $b->date_of_birth?->format('Y-m-d'),
            'blood_type' => $b->blood_type?->value,
            'has_chronic_condition' => $b->has_chronic_condition,
        ])->all();

        $this->hasFamilyDocument = (bool) $registration->family_status_document_path;
        $this->hasEmployeePhoto = (bool) $registration->employee_photo_path;
        $this->step = $registration->current_step ?: max(2, $this->determineResumeStep($registration));
    }

    protected function determineResumeStep(MedicalRegistration $registration): int
    {
        if ($registration->family_status_document_path) {
            return 6;
        }

        if ($registration->beneficiaries()->exists()) {
            return 5;
        }

        if ($registration->workplace) {
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
            'beneficiaryHasChronic', 'editingBeneficiaryIndex', 'familyStatusDocument',
            'employeePhoto', 'submitted', 'referenceNumber', 'hasFamilyDocument',
            'hasEmployeePhoto', 'hasSavedDraft',
        ]);

        $this->step = 1;
        $this->jobTitle = 'employee';
        $this->gender = 'male';
        $this->maritalStatus = 'married';
        $this->beneficiaryRelationship = 'spouse';
        $this->beneficiaryBloodType = 'a_positive';
    }

    protected function resetBeneficiaryForm(): void
    {
        $this->editingBeneficiaryIndex = null;
        $this->beneficiaryName = '';
        $this->beneficiaryRelationship = 'spouse';
        $this->beneficiaryNationalId = '';
        $this->beneficiaryDateOfBirth = '';
        $this->beneficiaryBloodType = 'a_positive';
        $this->beneficiaryHasChronic = false;
        $this->resetValidation([
            'beneficiaryName',
            'beneficiaryRelationship',
            'beneficiaryNationalId',
            'beneficiaryDateOfBirth',
            'beneficiaryBloodType',
        ]);
    }

    protected function notify(string $message): void
    {
        $this->toastMessage = $message;
    }
}
