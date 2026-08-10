<?php

use App\Http\Controllers\ReferenceCardController;
use App\Livewire\MedicalRegistrationForm;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/register');

Route::get('/register', MedicalRegistrationForm::class)
    ->middleware('registration.active')
    ->name('registration.form');

Route::get('/register/reference-card/{registration}', ReferenceCardController::class)
    ->middleware('registration.active')
    ->name('registration.reference-card');
