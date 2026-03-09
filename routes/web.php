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
    ->name('admin.contacts.sendProjectDetails')
    ->middleware('auth');

    
    // Sitemap
Route::get('/sitemap.xml', function () {
    $content = view('sitemap');
    return response($content, 200)->header('Content-Type', 'application/xml');
});

// Robots.txt
Route::get('/robots.txt', function () {
    $content = "User-agent: *\nAllow: /\nSitemap: " . config('app.url') . "/sitemap.xml";
    return response($content, 200)->header('Content-Type', 'text/plain');
});


// 2FA Route
Route::post('/2fa', function (\Illuminate\Http\Request $request) {
    return redirect('/halam-panel');
})->middleware(['web', '2fa']);

// Project detail / Case study
Route::get('/projects/{slug}', function ($slug) {
    $project = \App\Models\Project::where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

    $related = \App\Models\Project::where('is_active', true)
        ->where('id', '!=', $project->id)
        ->where(function($q) use ($project) {
            $q->where('category', $project->category);
        })
        ->limit(3)
        ->get();

    return Inertia::render('ProjectDetail', [
        'project' => $project,
        'related' => $related,
    ]);
})->name('project.show');
