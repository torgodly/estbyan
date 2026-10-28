<?php

use App\Http\Controllers\Api\AcceptedEmployeePhotoController;
use App\Http\Controllers\Api\AcceptedEmployeesController;
use Illuminate\Support\Facades\Route;

Route::middleware('accepted-registrations.key')->group(function (): void {
    Route::get('/accepted-employees', AcceptedEmployeesController::class);

    Route::get('/accepted-employees/{registration}/photo', [AcceptedEmployeePhotoController::class, 'employee'])
        ->name('api.accepted-employees.photo');

    Route::get('/accepted-employees/{registration}/family-members/{beneficiary}/photo', [AcceptedEmployeePhotoController::class, 'familyMember'])
        ->name('api.accepted-employees.family-member-photo');
});
