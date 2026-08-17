<?php

use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Settings\RegistrationSettings;
use App\Support\LibyanNationalId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();
});

it('redirects home to registration form', function () {
    $this->get('/')->assertRedirect('/register');
});

it('shows registration form when enabled', function () {
    $this->get('/register')
        ->assertSuccessful()
        ->assertSee('منظومة الاستبيان', false)
        ->assertSee('info@smartcare.com.ly', false)
        ->assertSee('0921623448', false)
        ->assertSee('إدارة الموارد البشرية بمصلحة الضرائب', false)
        ->assertSee('images/brand/smart-care.png', false)
        ->assertSee('images/brand/tax-authority.png', false)
        ->assertSee('reg-network-progress', false)
        ->assertSee('reg-loading-overlay', false)
        ->assertSee('لا يوجد اتصال بالإنترنت', false)
        ->assertSee('جاري التحقق', false);
});

it('shows closed page when form is disabled', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = false;
    $settings->disabled_message_ar = 'التسجيل مغلق للصيانة';
    $settings->disabled_message_en = 'Registration closed for maintenance';
    $settings->save();

    $this->get('/register')
        ->assertSuccessful()
        ->assertSee('التسجيل غير متاح حالياً', false)
        ->assertSee('التسجيل مغلق للصيانة', false)
        ->assertSee('Registration closed for maintenance', false)
        ->assertSee('og:image', false)
        ->assertSee('images/og-registration.png', false)
        ->assertSee('favicon-32x32.png', false)
        ->assertSee('images/brand/smart-care.png', false);
});

it('exposes open graph tags on the registration form for share previews', function () {
    $this->get('/register')
        ->assertSuccessful()
        ->assertSee('التسجيل الطبي — مصلحة الضرائب × SMART CARE', false)
        ->assertSee('موظفي مصلحة الضرائب الليبية', false)
        ->assertSee('og:site_name', false)
        ->assertSee('مصلحة الضرائب · SMART CARE', false)
        ->assertSee('og:title', false)
        ->assertSee('og:description', false)
        ->assertSee('og:image', false)
        ->assertSee('images/og-registration.png', false)
        ->assertSee('og:image:alt', false)
        ->assertSee('مصلحة الضرائب الليبية والرعاية الذكية', false)
        ->assertSee('twitter:card', false)
        ->assertSee('apple-touch-icon', false)
        ->assertDontSee('ديوان المحاسبة', false);
});

it('rejects invalid national id format at the gate', function () {
    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', '123')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasErrors('nationalId')
        ->assertSet('step', 1);
});

it('rejects non-employees at the identity gate', function () {
    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', '119900112233')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasErrors('nationalId')
        ->assertSet('step', 1);

    expect(MedicalRegistration::query()->count())->toBe(0);
});

it('unlocks the form for a valid employee and prefills locked fields', function () {
    $employee = Employee::factory()->create([
        'employee_number' => '2001',
        'national_id' => '119800507148',
        'full_name' => 'أحمد محمد',
        'workplace' => 'general_admin',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', '119800507148')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('employeeNumber', '2001')
        ->assertSet('verifiedFullName', 'أحمد محمد')
        ->assertSet('workplace', 'general_admin')
        ->assertSet('gender', 'male')
        ->assertSet('identityLocked', true);

    $registration = MedicalRegistration::query()->where('employee_number', '2001')->first();

    expect($registration)->not->toBeNull()
        ->and($registration->employee_id)->toBe($employee->id)
        ->and($registration->full_name)->toBe('أحمد محمد')
        ->and($registration->workplace)->toBe('general_admin')
        ->and($registration->gender)->toBe(Gender::Male);
});

it('persists step one draft in session and restores on remount', function () {
    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', '119700349522')
        ->set('consent', true)
        ->assertSet('hasSavedDraft', true);

    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('nationalId', '119700349522')
        ->assertSet('consent', true)
        ->assertSet('hasSavedDraft', true);
});

it('restores registration after refresh simulation', function () {
    Employee::factory()->create([
        'employee_number' => '3001',
        'national_id' => '219800123456',
        'full_name' => 'سارة علي',
        'workplace' => 'tripoli',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '3001')
        ->set('nationalId', '219800123456')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('dateOfBirth', '1980-01-01')
        ->set('city', 'tripoli')
        ->set('beneficiariesCount', '2')
        ->set('address', 'طرابلس')
        ->set('phone', '0912345678')
        ->call('saveEmployeeDetails')
        ->assertHasNoErrors();

    $registration = MedicalRegistration::query()->where('employee_number', '3001')->first();

    expect($registration)->not->toBeNull()
        ->and($registration->phone)->toBe('0912345678')
        ->and($registration->current_step)->toBe(3);

    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('step', 3)
        ->assertSet('phone', '0912345678')
        ->assertSet('verifiedFullName', 'سارة علي')
        ->assertSet('workplace', 'tripoli');
});

it('requires date of birth year to match national id', function () {
    Employee::factory()->create([
        'employee_number' => '3101',
        'national_id' => '119850112233',
        'full_name' => 'كريم سالم',
        'workplace' => 'general_admin',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '3101')
        ->set('nationalId', '119850112233')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('dateOfBirth', '1990-01-01')
        ->set('city', 'tripoli')
        ->set('beneficiariesCount', '0')
        ->set('address', 'طرابلس')
        ->set('phone', '0912345678')
        ->call('saveEmployeeDetails')
        ->assertHasErrors('dateOfBirth');
});

it('clears all form data and session', function () {
    Employee::factory()->create([
        'employee_number' => '4001',
        'national_id' => '119750111111',
        'full_name' => 'خالد أحمد',
        'workplace' => 'sebha',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '4001')
        ->set('nationalId', '119750111111')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->call('clearForm')
        ->assertSet('step', 1)
        ->assertSet('employeeNumber', '')
        ->assertSet('registrationId', null);

    expect(session('registration_id'))->toBeNull()
        ->and(MedicalRegistration::query()->where('employee_number', '4001')->exists())->toBeFalse();
});

it('saves a beneficiary with photo medical record and validated national id', function () {
    Storage::fake('local');

    Employee::factory()->create([
        'employee_number' => '5001',
        'national_id' => '219890080065',
        'full_name' => 'نادية حسن',
        'workplace' => 'general_admin',
    ]);

    $photo = UploadedFile::fake()->image('spouse.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '5001')
        ->set('nationalId', '219890080065')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'محمد حسن')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryNationalId', '119880112233')
        ->set('beneficiaryDateOfBirth', '1988-03-15')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryHasChronicConditions', true)
        ->set('beneficiaryChronicConditions', ['heart_disease'])
        ->set('beneficiaryHasTumor', false)
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertSet('showBeneficiaryForm', false)
        ->assertCount('beneficiaries', 1);

    $registration = MedicalRegistration::query()->where('employee_number', '5001')->first();
    $beneficiary = $registration->beneficiaries()->first();

    expect($beneficiary)->not->toBeNull()
        ->and($beneficiary->full_name)->toBe('محمد حسن')
        ->and($beneficiary->national_id)->toBe('119880112233')
        ->and($beneficiary->has_chronic_conditions)->toBeTrue()
        ->and($beneficiary->chronic_conditions)->toBe(['heart_disease'])
        ->and($beneficiary->photo_path)->not->toBeNull();

    Storage::disk('local')->assertExists($beneficiary->photo_path);
});

it('hides the beneficiary card while its edit form is open', function () {
    Storage::fake('local');

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1975);
    $beneficiaryNationalId = LibyanNationalId::generate(Gender::Female, 2000);

    Employee::factory()->create([
        'employee_number' => '5002',
        'national_id' => $employeeNationalId,
        'full_name' => 'سامي علي',
        'workplace' => 'general_admin',
    ]);

    $photo = UploadedFile::fake()->image('member.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '5002')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'فاطمة علي')
        ->set('beneficiaryRelationship', 'daughter')
        ->set('beneficiaryNationalId', $beneficiaryNationalId)
        ->set('beneficiaryDateOfBirth', '2000-01-10')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryHasChronicConditions', false)
        ->set('beneficiaryHasTumor', false)
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertSee('فاطمة علي')
        ->assertSeeHtml('wire:click="editBeneficiary(0)"')
        ->call('editBeneficiary', 0)
        ->assertSet('showBeneficiaryForm', true)
        ->assertSet('editingBeneficiaryIndex', 0)
        ->assertSee('تعديل مستفيد')
        ->assertDontSeeHtml('wire:click="editBeneficiary(0)"')
        ->assertDontSeeHtml('wire:click="deleteBeneficiary(0)"');
});

it('continues to review when documents are already saved without re-uploading', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1976);

    $employee = Employee::factory()->create([
        'employee_number' => '5005',
        'national_id' => $employeeNationalId,
        'full_name' => 'إبراهيم صالح',
        'workplace' => 'general_admin',
    ]);

    MedicalRegistration::factory()->create([
        'employee_id' => $employee->id,
        'employee_number' => '5005',
        'national_id' => $employeeNationalId,
        'full_name' => 'إبراهيم صالح',
        'workplace' => 'general_admin',
        'status' => RegistrationStatus::Draft,
        'current_step' => 6,
        'family_status_document_path' => 'registrations/demo/family.pdf',
        'employee_photo_path' => 'registrations/demo/employee.jpg',
        'date_of_birth' => '1976-04-12',
        'phone' => '0912345678',
        'city' => 'tripoli',
        'address' => 'طرابلس',
        'beneficiaries_count' => 0,
        'consent_at' => now(),
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '5005')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 5)
        ->assertSee('صورة من شهادة الوضع العائلي')
        ->assertSet('hasFamilyDocument', true)
        ->assertSet('hasEmployeePhoto', true)
        ->call('saveDocuments')
        ->assertHasNoErrors()
        ->assertSet('step', 6);
});

it('reserves scroll space under the fixed mobile action sheet on the final report', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1972);

    Employee::factory()->create([
        'employee_number' => '5004',
        'national_id' => $employeeNationalId,
        'full_name' => 'يوسف أحمد',
        'workplace' => 'general_admin',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '5004')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 6)
        ->assertSee('المستندات')
        ->assertSeeHtml('class="reg-actions"')
        ->assertSeeHtml('class="reg-actions-dock"')
        ->assertSee('تأكيد وإرسال التسجيل')
        ->assertSee('جاري الإرسال')
        ->assertDontSee('حفظ كمسودة')
        ->assertDontSee('جاري الحفظ');
});

it('renders a readable beneficiaries review section on the final report', function () {
    Storage::fake('local');

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1978);
    $beneficiaryNationalId = LibyanNationalId::generate(Gender::Female, 1985);

    Employee::factory()->create([
        'employee_number' => '5003',
        'national_id' => $employeeNationalId,
        'full_name' => 'خالد منصور',
        'workplace' => 'general_admin',
    ]);

    $photo = UploadedFile::fake()->image('spouse.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '5003')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'منى منصور')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryNationalId', $beneficiaryNationalId)
        ->set('beneficiaryDateOfBirth', '1985-06-20')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryHasChronicConditions', true)
        ->set('beneficiaryChronicConditions', ['heart_disease'])
        ->set('beneficiaryHasTumor', false)
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->set('step', 6)
        ->assertSee('أفراد العائلة المسجلون')
        ->assertSee('منى منصور')
        ->assertSee('بيانات الهوية')
        ->assertSee('السجل الطبي')
        ->assertSee('1 ملاحظة طبية')
        ->assertSee('الأمراض المزمنة المحددة')
        ->assertSee('دخول مستشفى (12 شهر)');
});

it('shows only the success page when logging in with a submitted registration', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1988);

    $employee = Employee::factory()->create([
        'employee_number' => '6001',
        'national_id' => $employeeNationalId,
        'full_name' => 'عمر سالم',
        'workplace' => 'misrata',
    ]);

    MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => '6001',
        'national_id' => $employeeNationalId,
        'full_name' => 'عمر سالم',
        'workplace' => 'misrata',
        'reference_number' => 'SC26-00042',
        'date_of_birth' => '1988-05-05',
        'phone' => '0911111111',
        'city' => 'misrata',
        'address' => 'مصراته',
        'beneficiaries_count' => 0,
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '6001')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('submitted', true)
        ->assertSet('referenceNumber', 'SC26-00042')
        ->assertSee('تم إرسال التسجيل بنجاح')
        ->assertDontSee('تقرير المراجعة النهائية');
});

it('restores the success page on reload for a submitted registration', function () {
    $employee = Employee::factory()->create([
        'national_id' => LibyanNationalId::generate(Gender::Male, 1979),
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => $employee->full_name,
        'reference_number' => 'SC26-00055',
    ]);

    $this->withSession([
        'registration_id' => $registration->id,
        'registration_gate_passed' => true,
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('submitted', true)
        ->assertSet('referenceNumber', 'SC26-00055')
        ->assertSet('toastMessage', null)
        ->assertSee('تم إرسال التسجيل بنجاح')
        ->assertSee('تعديل الطلب');
});

it('keeps the login gate clean on refresh when identity was not verified this session', function () {
    $employee = Employee::factory()->create([
        'national_id' => LibyanNationalId::generate(Gender::Male, 1975),
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => $employee->full_name,
        'reference_number' => 'SC26-00091',
    ]);

    // Stale registration id in session without passing the login gate again.
    $this->withSession([
        'registration_id' => $registration->id,
        'reference_download_id' => $registration->id,
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('submitted', false)
        ->assertSet('registrationId', null)
        ->assertSet('step', 1)
        ->assertSet('toastMessage', null)
        ->assertSee('تسجيل الدخول للموظفين')
        ->assertDontSee('تم إرسال التسجيل بنجاح')
        ->assertDontSee('طلبك مُرسَل مسبقاً');
});

it('starts editing a submitted registration from the first form step with data filled', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1985);

    $employee = Employee::factory()->create([
        'employee_number' => '6002',
        'national_id' => $employeeNationalId,
        'full_name' => 'سامي عمر',
        'workplace' => 'general_admin',
    ]);

    MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => '6002',
        'national_id' => $employeeNationalId,
        'full_name' => 'سامي عمر',
        'workplace' => 'general_admin',
        'reference_number' => 'SC26-00066',
        'date_of_birth' => '1985-03-10',
        'phone' => '0922222222',
        'city' => 'tripoli',
        'address' => 'طرابلس',
        'beneficiaries_count' => 0,
        'job_title' => 'employee',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '6002')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('submitted', true)
        ->call('editSubmittedRegistration')
        ->assertSet('submitted', false)
        ->assertSet('step', 2)
        ->assertSet('identityLocked', true)
        ->assertSet('verifiedFullName', 'سامي عمر')
        ->assertSet('phone', '0922222222')
        ->assertSet('referenceNumber', 'SC26-00066')
        ->assertSee('بيانات الموظف');
});

it('does not trap verified users on the login gate when current_step is 1', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1984);

    $employee = Employee::factory()->create([
        'employee_number' => '6010',
        'national_id' => $employeeNationalId,
        'full_name' => 'نادر سليمان',
        'workplace' => 'general_admin',
    ]);

    MedicalRegistration::factory()->create([
        'employee_id' => $employee->id,
        'employee_number' => '6010',
        'national_id' => $employeeNationalId,
        'full_name' => 'نادر سليمان',
        'workplace' => 'general_admin',
        'status' => RegistrationStatus::Editing,
        'reference_number' => 'SC26-00088',
        'current_step' => 1,
        'date_of_birth' => '1984-06-01',
        'phone' => '0912223344',
        'city' => 'tripoli',
        'address' => 'طرابلس',
        'beneficiaries_count' => 0,
        'consent_at' => now(),
        'family_status_document_path' => 'registrations/demo/family.pdf',
        'employee_photo_path' => 'registrations/demo/employee.jpg',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '6010')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasNoErrors()
        ->assertSet('submitted', false)
        ->assertSet('identityLocked', true)
        ->assertSet('registrationId', fn ($id) => filled($id))
        ->assertSet('step', 6)
        ->assertDontSee('تسجيل الدخول للموظفين')
        ->assertSee('بيانات الموظف')
        ->assertSee('تم استعادة طلبك');
});

it('blocks going back to the login gate after identity is verified', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1977);

    $employee = Employee::factory()->create([
        'employee_number' => '6011',
        'national_id' => $employeeNationalId,
        'full_name' => 'كريم فرج',
        'workplace' => 'general_admin',
    ]);

    MedicalRegistration::factory()->create([
        'employee_id' => $employee->id,
        'employee_number' => '6011',
        'national_id' => $employeeNationalId,
        'full_name' => 'كريم فرج',
        'workplace' => 'general_admin',
        'status' => RegistrationStatus::Draft,
        'current_step' => 2,
        'consent_at' => now(),
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '6011')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('step', 2)
        ->call('goBack')
        ->assertSet('step', 2)
        ->assertDontSee('تسجيل الدخول للموظفين');
});

it('keeps edit mode after refresh instead of returning to the success page', function () {
    $employee = Employee::factory()->create([
        'national_id' => LibyanNationalId::generate(Gender::Male, 1982),
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => $employee->full_name,
        'reference_number' => 'SC26-00077',
        'date_of_birth' => '1982-04-12',
        'phone' => '0912345678',
        'city' => 'tripoli',
        'address' => 'طرابلس',
        'beneficiaries_count' => 0,
        'current_step' => 6,
    ]);

    $this->withSession([
        'registration_id' => $registration->id,
        'registration_gate_passed' => true,
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('submitted', true)
        ->call('editSubmittedRegistration')
        ->assertSet('submitted', false)
        ->assertSet('step', 2)
        ->assertSee('بيانات الموظف')
        ->assertDontSee('تم إرسال التسجيل بنجاح');

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Editing)
        ->and($registration->fresh()->current_step)->toBe(2)
        ->and(session('registration_editing'))->toBeTrue();

    // Simulate a full page refresh with the same session.
    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('submitted', false)
        ->assertSet('step', 2)
        ->assertSet('referenceNumber', 'SC26-00077')
        ->assertSee('بيانات الموظف')
        ->assertDontSee('تم إرسال التسجيل بنجاح');

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Editing);
});

it('keeps the same reference number when resubmitting after edit', function () {
    Storage::fake('local');

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1988);

    $employee = Employee::factory()->create([
        'employee_number' => '6001',
        'national_id' => $employeeNationalId,
        'full_name' => 'عمر سالم',
        'workplace' => 'misrata',
    ]);

    $registration = MedicalRegistration::factory()->create([
        'employee_id' => $employee->id,
        'employee_number' => '6001',
        'national_id' => $employeeNationalId,
        'full_name' => 'عمر سالم',
        'workplace' => 'misrata',
        'status' => RegistrationStatus::Submitted,
        'reference_number' => 'SC26-00042',
        'submitted_at' => now()->subDay(),
        'family_status_document_path' => 'registrations/demo/family.pdf',
        'employee_photo_path' => 'registrations/demo/employee.jpg',
        'current_step' => 6,
        'consent_at' => now(),
        'date_of_birth' => '1988-05-05',
        'phone' => '0911111111',
        'city' => 'misrata',
        'address' => 'مصراته',
        'beneficiaries_count' => 0,
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '6001')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('submitted', true)
        ->assertSet('referenceNumber', 'SC26-00042')
        ->call('editSubmittedRegistration')
        ->assertSet('submitted', false)
        ->assertSet('step', 2)
        ->call('submitRegistration')
        ->assertSet('submitted', true)
        ->assertSet('referenceNumber', 'SC26-00042');

    expect($registration->fresh()->reference_number)->toBe('SC26-00042')
        ->and($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);
});

it('blocks editing when the registration is approved', function () {
    $employee = Employee::factory()->create([
        'employee_number' => '7001',
        'national_id' => '219900556677',
        'full_name' => 'ليلى فرج',
        'workplace' => 'general_admin',
    ]);

    MedicalRegistration::factory()->approved()->create([
        'employee_id' => $employee->id,
        'employee_number' => '7001',
        'national_id' => '219900556677',
        'full_name' => 'ليلى فرج',
        'workplace' => 'general_admin',
        'reference_number' => 'SC26-00077',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '7001')
        ->set('nationalId', '219900556677')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('approvedLocked', true)
        ->assertSet('step', 1)
        ->assertSet('referenceNumber', 'SC26-00077');
});

it('logs out from the success page without deleting the registration', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1981);

    $employee = Employee::factory()->create([
        'employee_number' => '6020',
        'national_id' => $employeeNationalId,
        'full_name' => 'راشد منصور',
        'workplace' => 'general_admin',
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => '6020',
        'national_id' => $employeeNationalId,
        'full_name' => 'راشد منصور',
        'workplace' => 'general_admin',
        'reference_number' => 'SC26-00099',
        'date_of_birth' => '1981-02-02',
        'phone' => '0913334444',
        'city' => 'tripoli',
        'address' => 'طرابلس',
        'beneficiaries_count' => 0,
        'employee_photo_path' => 'registrations/demo/employee.jpg',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '6020')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('submitted', true)
        ->assertSee('تسجيل الخروج')
        ->call('logout')
        ->assertSet('submitted', false)
        ->assertSet('registrationId', null)
        ->assertSet('step', 1)
        ->assertSet('toastMessage', 'تم تسجيل الخروج بنجاح')
        ->assertSee('تسجيل الدخول للموظفين')
        ->assertDontSee('تم إرسال التسجيل بنجاح');

    expect($registration->fresh())->not->toBeNull()
        ->and(session('registration_gate_passed'))->toBeNull()
        ->and(session('registration_id'))->toBeNull();
});

it('downloads a reference card for the session registration', function () {
    $employee = Employee::factory()->create([
        'national_id' => LibyanNationalId::generate(),
    ]);
    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => $employee->full_name,
        'reference_number' => 'SC26-00123',
        'employee_photo_path' => 'registrations/demo/employee.jpg',
    ]);

    $this->withSession([
        'registration_id' => $registration->id,
        'reference_download_id' => $registration->id,
    ])
        ->get(route('registration.reference-card', $registration))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/png');
});
