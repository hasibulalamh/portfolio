<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\ApiShowcaseController;
use App\Http\Controllers\Admin\ContactInfoController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ConversionsController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\HeroController;
use App\Http\Controllers\Admin\MeetingRequestController;
use App\Http\Controllers\Admin\OrphanFileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SectionVisibilityController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SkillCategoryController;
use App\Http\Controllers\Admin\SkillController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\TimelineItemController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClickTrackingController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Registered with an /api prefix, so `/login` here is reachable at
| http://localhost:8000/api/login — which is what both frontends'
| NEXT_PUBLIC_API_URL points at.
|
| The admin paths below were taken from what portfolio-admin actually calls
| (lib/api.js plus every page under app/admin), not from a spec. Where the
| two disagreed, the frontend won:
|
|   /admin/timeline-items  (not /admin/timeline)
|   /admin/api-showcases   (not /admin/api-showcase)
|
*/

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/admin/me', [AuthController::class, 'me']);
});

// ---------------------------------------------------------------------------
// Public — read-only content for portfolio-frontend, plus the two visitor forms
// ---------------------------------------------------------------------------
Route::get('/settings', [PublicController::class, 'settings']);
Route::get('/section-visibility', [PublicController::class, 'sectionVisibility']);
Route::get('/hero', [PublicController::class, 'hero']);
Route::get('/about', [PublicController::class, 'about']);
Route::get('/skills', [PublicController::class, 'skills']);
Route::get('/timeline', [PublicController::class, 'timeline']);
Route::get('/projects', [PublicController::class, 'projects']);
Route::get('/projects/{slug}', [PublicController::class, 'project']);
Route::get('/api-showcases', [PublicController::class, 'apiShowcases']);
Route::get('/testimonials', [PublicController::class, 'testimonials']);
Route::get('/contact-info', [PublicController::class, 'contactInfo']);

// Visitor submissions. Rate limited because these are unauthenticated writes
// reachable by anyone on the internet.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/contact-messages', [PublicController::class, 'storeContactMessage']);
    Route::post('/meeting-requests', [PublicController::class, 'storeMeetingRequest']);
});

// CTA click tracking. Unauthenticated by design — the caller is an anonymous
// visitor — and throttled harder than the forms because every page view can
// fire it and there is nothing a client legitimately needs thirty times a
// minute. Accepts only a fixed allow-list of event types; anything else is a
// 422, never a row.
Route::post('/track', [ClickTrackingController::class, 'store'])
    ->middleware('throttle:30,1');

// ---------------------------------------------------------------------------
// Admin — every route below requires a valid Sanctum token
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

    // Site settings (singleton)
    Route::get('/settings', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);
    Route::post('/settings/reset', [SettingController::class, 'reset']);

    // Section visibility and ordering — the source of truth for both which
    // homepage sections render and which nav links exist. Rows are fixed (one
    // per section, created by migration), so there is no create or delete: the
    // whole list is posted back on every toggle or reorder.
    //
    // This replaced the old nav_items CRUD. Every row that table held pointed
    // at a homepage section, so keeping both meant two places to edit and no
    // guarantee they agreed.
    Route::get('/section-visibility', [SectionVisibilityController::class, 'index']);
    Route::put('/section-visibility', [SectionVisibilityController::class, 'update']);

    // Hero (singleton)
    Route::get('/hero', [HeroController::class, 'show']);
    Route::put('/hero', [HeroController::class, 'update']);
    Route::post('/hero/reset', [HeroController::class, 'reset']);

    // About (singleton)
    Route::get('/about', [AboutController::class, 'show']);
    Route::put('/about', [AboutController::class, 'update']);
    Route::post('/about/reset', [AboutController::class, 'reset']);

    // Skill categories
    Route::get('/skill-categories', [SkillCategoryController::class, 'index']);
    Route::post('/skill-categories', [SkillCategoryController::class, 'store']);
    Route::put('/skill-categories/reorder', [SkillCategoryController::class, 'reorder']);
    Route::put('/skill-categories/{skillCategory}', [SkillCategoryController::class, 'update']);
    Route::delete('/skill-categories/{skillCategory}', [SkillCategoryController::class, 'destroy']);

    // Skills
    Route::get('/skills', [SkillController::class, 'index']);
    Route::post('/skills', [SkillController::class, 'store']);
    Route::put('/skills/reorder', [SkillController::class, 'reorder']);
    Route::put('/skills/{skill}', [SkillController::class, 'update']);
    Route::delete('/skills/{skill}', [SkillController::class, 'destroy']);

    // Timeline
    Route::get('/timeline-items', [TimelineItemController::class, 'index']);
    Route::post('/timeline-items', [TimelineItemController::class, 'store']);
    Route::put('/timeline-items/reorder', [TimelineItemController::class, 'reorder']);
    Route::put('/timeline-items/{timelineItem}', [TimelineItemController::class, 'update']);
    Route::delete('/timeline-items/{timelineItem}', [TimelineItemController::class, 'destroy']);

    // Projects
    // Bound as {project:id}: Project::getRouteKeyName() is 'slug' for public
    // URLs, but the admin panel addresses projects by numeric id.
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/reorder', [ProjectController::class, 'reorder']);
    Route::put('/projects/{project:id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project:id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project:id}/case-study', [ProjectController::class, 'saveCaseStudy']);

    // API showcases
    Route::get('/api-showcases', [ApiShowcaseController::class, 'index']);
    Route::post('/api-showcases', [ApiShowcaseController::class, 'store']);
    Route::put('/api-showcases/reorder', [ApiShowcaseController::class, 'reorder']);
    Route::put('/api-showcases/{apiShowcase}', [ApiShowcaseController::class, 'update']);
    Route::delete('/api-showcases/{apiShowcase}', [ApiShowcaseController::class, 'destroy']);

    // Testimonials
    Route::get('/testimonials', [TestimonialController::class, 'index']);
    Route::post('/testimonials', [TestimonialController::class, 'store']);
    Route::put('/testimonials/reorder', [TestimonialController::class, 'reorder']);
    Route::put('/testimonials/{testimonial}', [TestimonialController::class, 'update']);
    Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy']);

    // Contact info (singleton)
    Route::get('/contact-info', [ContactInfoController::class, 'show']);
    Route::put('/contact-info', [ContactInfoController::class, 'update']);
    Route::post('/contact-info/reset', [ContactInfoController::class, 'reset']);

    // Contact message inbox
    Route::get('/messages', [ContactMessageController::class, 'index']);
    Route::put('/messages/{message}/read', [ContactMessageController::class, 'toggleRead']);
    // Reply lives under /contact-messages rather than /messages to match the
    // public submit route's naming for this resource.
    Route::post('/contact-messages/{message}/reply', [ContactMessageController::class, 'reply']);
    Route::delete('/messages/{message}', [ContactMessageController::class, 'destroy']);

    // Meeting request inbox
    Route::get('/meeting-requests', [MeetingRequestController::class, 'index']);
    Route::put('/meeting-requests/{meetingRequest}/reply', [MeetingRequestController::class, 'reply']);
    Route::put('/meeting-requests/{meetingRequest}/note', [MeetingRequestController::class, 'note']);
    Route::delete('/meeting-requests/{meetingRequest}', [MeetingRequestController::class, 'destroy']);

    // Generic file upload
    Route::post('/upload', [UploadController::class, 'store']);

    // System health check — admin-only, no database access or logging.
    Route::get('/health', [HealthController::class, 'index']);

    // Aggregate CTA click counts — admin-only read over the click_events table.
    Route::get('/conversions', [ConversionsController::class, 'index']);

    // Storage hygiene — scan for unreferenced uploads; deletion requires an
    // explicit path list and re-verifies each path server-side before removal.
    Route::get('/storage/orphans', [OrphanFileController::class, 'index']);
    Route::delete('/storage/orphans', [OrphanFileController::class, 'destroy']);
});
