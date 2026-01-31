<?php
    
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home page (public)
Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');


