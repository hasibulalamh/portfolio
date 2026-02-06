<?php

namespace App\Http\Middleware;

use App\Models\NavbarItem;
use App\Models\SkillSetting as SkillSettingModel;
use Illuminate\Http\Request;
use Inertia\Middleware;


class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Share navbar items globally
            'navbarItems' => NavbarItem::active()
                ->ordered()
                ->get(['name', 'href', 'icon']),

                  // Footer items grouped by category
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
            //hero section settings
            'heroSettings' => \App\Models\HeroSetting::getActive(),

            //about section settings
            'aboutSettings' => \App\Models\AboutSetting::active(),

            //skills section settings
           'skills' => \App\Models\Skill::active()
            ->ordered()
            ->get(['name', 'category', 'icon', 'color', 'proficiency', 'is_featured']),

            //projects  section
            'projects' => \App\Models\Project::active()
            ->ordered()
            ->get(['id', 'title','slug','description','category','technologies','featured_image','live_url','github_url','is_featured','completed_at']),
        ];
    }
}
