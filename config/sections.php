<?php
return [
    'mappings' => [
        'Hero'         => 'HeroSetting',
        'About'        => 'AboutSetting',
        'Skills'       => 'Skill',
        'Projects'     => 'Project',
        'Services'     => 'Service',
        'Experience'   => 'Experience',
        'Testimonials' => 'Testimonial',
        'Contact'      => 'ContactSetting',
    ],

    // ← এটা নতুন add করো
    'order' => [
        'Hero'         => 1,
        'About'        => 2,
        'Skills'       => 3,
        'Projects'     => 4,
        'Services'     => 5,
        'Experience'   => 6,
        'Testimonials' => 7,
        'Contact'      => 8,
    ],

    'component_path' => resource_path('js/Components/Home'),
    'auto_sync' => env('SECTIONS_AUTO_SYNC', false),
];
