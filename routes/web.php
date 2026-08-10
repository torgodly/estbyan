<?php

use App\Http\Controllers\ReferenceCardController;
use App\Http\Controllers\RegistrationDocumentController;
use App\Livewire\MedicalRegistrationForm;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/register');

Route::get('/register', MedicalRegistrationForm::class)
    ->middleware('registration.active')
    ->name('registration.form');

Route::get('/register/reference-card/{registration}', ReferenceCardController::class)
    ->middleware('registration.active')
    ->name('registration.reference-card');

Route::get('/register/documents/{registration}/{document}', [RegistrationDocumentController::class, 'show'])
    ->whereIn('document', ['family-status', 'employee-photo'])
    ->name('registration.documents.show');

Route::get('/register/documents/{registration}/beneficiaries/{beneficiary}', [RegistrationDocumentController::class, 'beneficiary'])
    ->name('registration.documents.beneficiary');
