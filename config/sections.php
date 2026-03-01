<?php
return [
    'mappings' => [
        'Hero'         => 'HeroSetting',
        'About'        => 'AboutSetting',
        'Skills'       => 'Skill',
        'Experience'   => 'Experience',
        'Projects'     => 'Project',
        'Testimonials' => 'Testimonial',
        'Contact'      => 'ContactSetting',
    ],

    // ← এটা নতুন add করো
    'order' => [
        'Hero'         => 1,
        'About'        => 2,
        'Skills'       => 3,
        'Experience'   => 4,
        'Projects'     => 5,
        'Testimonials' => 6,
        'Contact'      => 7,
    ],

    'component_path' => resource_path('js/Components/Home'),
    'auto_sync' => env('SECTIONS_AUTO_SYNC', false),
];
