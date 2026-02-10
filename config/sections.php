<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Homepage Section Mappings
    |--------------------------------------------------------------------------
    |
    | Map Vue component names to their corresponding Laravel models.
    | Add new sections here when creating new homepage components.
    |
    */

    'mappings' => [
        // Core Sections
        'Hero' => 'HeroSetting',
        'About' => 'AboutSetting',
        'Skills' => 'Skill',
        'Projects' => 'Project',

        // Future Sections (uncomment when needed)
        // 'Experience' => 'Experience',
        // 'Education' => 'Education',
        // 'Testimonials' => 'Testimonial',
        // 'Services' => 'Service',
        // 'Contact' => 'ContactForm',
        // 'Blog' => 'Post',
        // 'Certifications' => 'Certification',
        // 'Awards' => 'Award',
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Path
    |--------------------------------------------------------------------------
    |
    | The directory path where homepage section components are located.
    |
    */

    'component_path' => resource_path('js/Components/Home'),

    /*
    |--------------------------------------------------------------------------
    | Auto-Sync on Boot
    |--------------------------------------------------------------------------
    |
    | Automatically sync sections when application boots.
    | Disable in production for better performance.
    |
    */

    'auto_sync' => env('SECTIONS_AUTO_SYNC', false),
];
