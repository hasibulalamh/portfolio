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

// AI Chat
Route::post('/ai-chat', function (\Illuminate\Http\Request $request) {
    $request->validate(['message' => 'required|string|max:500']);

    $apiKey = env('GEMINI_API_KEY');
    $message = $request->input('message');

    $systemPrompt = "You are an AI assistant for Hasibul Alam's portfolio website. Answer questions about Hasibul professionally and concisely.

About Hasibul Alam:
- Full Stack Web Developer based in Dhaka, Bangladesh
- Skills: Laravel, Vue.js, Inertia.js, Tailwind CSS, Filament v3, MySQL, PHP OOP, REST API
- Experience: Laravel Web Developer Intern at Kodeeo Ltd (Feb 2025 - Jun 2025)
- Education: B.Sc. in CSE at Northern University Bangladesh (2024-Present), Diploma in Engineering at CCN Polytechnic Institute (2018-2023)
- Available for: Full-time jobs and freelance projects
- Contact: hasibulalam108@gmail.com, WhatsApp: 01735-596980
- GitHub: github.com/HASIBULALAMH
- LinkedIn: linkedin.com/in/hasibul-alam-web-dev
- Projects: Portfolio website (Laravel+Vue+Inertia), E-commerce platform (Laravel)

Rules:
- Answer only questions related to Hasibul or web development
- Keep answers short (2-3 sentences max)
- Be professional and friendly
- If asked something unrelated, politely redirect to Hasibul's work
- Always respond in the same language the user writes in (Bengali or English)";

    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
        'system_instruction' => [
            'parts' => [['text' => $systemPrompt]]
        ],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $message]]]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 200,
            'temperature' => 0.7,
        ]
    ]);

    if ($response->successful()) {
        $text = $response->json('candidates.0.content.parts.0.text') ?? 'Sorry, I could not process your request.';
        return response()->json(['reply' => $text]);
    }

    return response()->json(['reply' => 'Sorry, something went wrong. Please try again.'], 500);
})->name('ai.chat');
