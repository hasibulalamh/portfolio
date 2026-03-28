<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;  
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    // ✅ ENTIRE FUNCTION REPLACED (Line 16-126)
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
            ],

            // ✅ Cached: Navbar items (60 minutes)
            'navbarItems' => Cache::remember('navbar_items', 3600, function () {
                return \App\Models\NavbarItem::active()
                    ->ordered()
                    ->get(['name', 'href', 'icon']);
            }),

            // ✅ Cached: Footer items (60 minutes)
            'footerItems' => Cache::remember('footer_items', 3600, function () {
                return [
                    'quickLinks' => \App\Models\FooterItem::active()
                        ->byCategory('quick_links')
                        ->ordered()
                        ->get(['name', 'value']),

                    'social' => \App\Models\FooterItem::active()
                        ->byCategory('social')
                        ->ordered()
                        ->get(['name', 'value', 'icon']),

                    'contact' => \App\Models\FooterItem::active()
                        ->byCategory('contact')
                        ->ordered()
                        ->get(['name', 'value']),
                ];
            }),

            // ✅ Cached: Hero section (30 minutes)
            'heroSettings' => Cache::remember('hero_settings', 1800, function () {
                return \App\Models\HeroSetting::getActive();
            }),

            // ✅ Cached: About section (30 minutes)
            'aboutSettings' => Cache::remember('about_settings', 1800, function () {
                return \App\Models\AboutSetting::getActive();
            }),

            // ✅ Cached: Skills (24 hours)
            'skills' => Cache::remember('skills_data', 86400, function () {
                return \App\Models\Skill::active()
                    ->ordered()
                    ->get(['id', 'name', 'category', 'icon', 'color', 'proficiency']);
            }),

            // ✅ Cached: Projects (24 hours)
            'projects' => Cache::remember('projects_data', 86400, function () {
                return \App\Models\Project::active()
                    ->ordered()
                    ->get([
                        'id',
                        'title',
                        'slug',
                        'description',
                        'category',
                        'technologies',
                        'featured_image',
                        'live_url',
                        'github_url',
                        'is_featured',
                        'completed_at'
                    ]);
            }),

            // ✅ Cached: Experience (24 hours)
            'experiences' => Cache::remember('experiences_data', 86400, function () {
                return \App\Models\Experience::active()
                    ->ordered()
                    ->get([
                        'id',
                        'type',
                        'title',
                        'company',
                        'company_logo',
                        'location',
                        'start_date',
                        'end_date',
                        'is_current',
                        'description',
                        'responsibilities',
                        'technologies',
                        'achievements',
                        'website_url',
                    ]);
            }),

            // ✅ Cached: Testimonials (24 hours)
            'testimonials' => Cache::remember('testimonials_data', 86400, function () {
                return \App\Models\Testimonial::active()
                    ->ordered()
                    ->get([
                        'id', 'client_name', 'client_role', 'client_company',
                        'client_photo', 'content', 'rating', 'project_type', 'date'
                    ]);
            }),

            // ✅ Cached: Services (24 hours)
            'services' => Cache::remember('services_data', 86400, function () {
                return \App\Models\Service::active()
                    ->orderBy('order')
                    ->orderBy('created_at')
                    ->get([
                        'id', 'title', 'slug', 'icon',
                        'short_description', 'technologies',
                        'scope_type', 'scope_description',
                        'pricing_note', 'min_duration',
                        'max_duration', 'is_featured'
                    ]);
            }),

            // ✅ Cached: Social links (24 hours)
            'social_links' => Cache::remember('social_links_data', 86400, function () {
                return \App\Models\SocialLink::active()
                    ->get(['platform', 'url', 'icon', 'color', 'show_in_hero', 'show_in_contact', 'show_in_footer']);
            }),

            // ✅ Cached: Contact section (12 hours)
            'contactSettings' => Cache::remember('contact_settings', 43200, function () {
                return \App\Models\ContactSetting::getActive();
            }),

            // ✅ Cached: Homepage sections (12 hours)
            'homepageSections' => Cache::remember('homepage_sections', 43200, function () {
                return \App\Models\SectionSetting::getEnabledSectionsWithData();
            }),
        ];
    }
}
