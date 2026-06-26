<?php

use App\Livewire\MedicalRegistrationForm;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/register');

Route::get('/register', MedicalRegistrationForm::class)
    ->middleware('registration.active')
    ->name('registration.form');
