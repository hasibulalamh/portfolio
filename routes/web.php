<?php
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::post('/admin/contacts/{id}/send-project-details',
    [ContactController::class, 'sendProjectDetails'])
    ->name('admin.contacts.sendProjectDetails')
    ->middleware('auth');

Route::get('/sitemap.xml', function () {
    $content = view('sitemap');
    return response($content, 200)->header('Content-Type', 'application/xml');
});

Route::get('/robots.txt', function () {
    $content = "User-agent: *\nAllow: /\nSitemap: " . config('app.url') . "/sitemap.xml";
    return response($content, 200)->header('Content-Type', 'text/plain');
});

Route::post('/2fa', function (\Illuminate\Http\Request $request) {
    return redirect('/halam-panel');
})->middleware(['web', '2fa']);

// Project Case Study
Route::get('/projects/{slug}', function ($slug) {
    $project = \App\Models\Project::where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

    $related = \App\Models\Project::where('is_active', true)
        ->where('id', '!=', $project->id)
        ->where('category', $project->category)
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

    // Rate limiting - max 20 requests per minute per IP
    $key = 'ai-chat:' . $request->ip();
    if (RateLimiter::tooManyAttempts($key, 20)) {
        return response()->json([
            'reply' => 'Too many requests. Please try again in a few minutes.'
        ], 429);
    }
    RateLimiter::hit($key, 60);

    // Validate API key exists
    $apiKey = env('GEMINI_API_KEY');
    if (!$apiKey) {
        Log::error('GEMINI_API_KEY not configured');
        return response()->json([
            'reply' => 'AI service is not available. Please contact admin.'
        ], 503);
    }

    $message = htmlspecialchars($request->input('message'), ENT_QUOTES, 'UTF-8');

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

Rules:
- Answer only questions related to Hasibul or web development
- Keep answers short (2-3 sentences max)
- Be professional and friendly
- Always respond in the same language the user writes in (Bengali or English)";

    try {
        $response = \Illuminate\Support\Facades\Http::timeout(10)
            ->retry(3, 100)
                    ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
                ])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent", [
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

        if (!$response->successful()) {
            Log::warning('Gemini API error', ['status' => $response->status()]);
            return response()->json([
                'reply' => 'The AI service is temporarily unavailable. Please try again.'
            ], 503);
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (!$text) {
            return response()->json([
                'reply' => 'Sorry, I could not generate a response. Please try again.'
            ], 500);
        }

        return response()->json(['reply' => $text]);

    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        Log::error('Gemini API connection failed', ['error' => $e->getMessage()]);
        return response()->json([
            'reply' => 'Connection error. Please try again.'
        ], 503);

    } catch (\Exception $e) {
        Log::error('AI chat error', ['error' => $e->getMessage()]);
        return response()->json([
            'reply' => 'An unexpected error occurred. Please try again.'
        ], 500);
    }
})->name('ai.chat');
