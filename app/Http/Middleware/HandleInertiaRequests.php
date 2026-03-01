<?php

namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
            ],

            // Navbar items
            'navbarItems' => \App\Models\NavbarItem::active()
                ->ordered()
                ->get(['name', 'href', 'icon']),

            // Footer items
            'footerItems' => [
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
            ],

            // Hero section
            'heroSettings' => \App\Models\HeroSetting::getActive(),

            // About section
            'aboutSettings' => \App\Models\AboutSetting::getActive(),

            // Skills section
            'skills' => \App\Models\Skill::active()
                ->ordered()
                ->get(['id', 'name', 'category', 'icon', 'color', 'proficiency']),

            // Projects section
            'projects' => \App\Models\Project::active()
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
                ]),

            // Experience section
            'experiences' => \App\Models\Experience::active()
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
                ]),

            // Contact section
           'contactSettings' => \App\Models\ContactSetting::getActive(),

            // Homepage Sections
            'homepageSections' => \App\Models\SectionSetting::getEnabledSectionsWithData(),
        ];
    }
}
