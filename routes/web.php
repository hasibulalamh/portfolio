<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home page (public)
Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::post('/admin/contacts/{id}/send-project-details',
    [ContactController::class, 'sendProjectDetails'])
    ->name('admin.contacts.sendProjectDetails');
