<?php

namespace App\Services;

class EmailServiceMatcher
{
    public static function getSuggestedServices(string $message): array
    {
        $message = strtolower($message);
        $services = [];

        $definitions = [
            [
                'keywords'    => ['web', 'website', 'laravel', 'vue', 'portal', 'dashboard', 'system'],
                'name'        => 'Web Development',
                'icon'        => '🌐',
                'description' => 'Custom Laravel + Vue.js web application tailored to your business needs.',
                'features'    => ['Responsive Design', 'Admin Dashboard', 'Database Integration', 'REST API', 'Authentication System'],
                'price'       => '$500 - $2,000',
                'duration'    => '2-4 weeks',
            ],
            [
                'keywords'    => ['ecommerce', 'e-commerce', 'shop', 'store', 'sell', 'product', 'cart', 'payment'],
                'name'        => 'E-Commerce Solution',
                'icon'        => '🛒',
                'description' => 'Full-featured online store with secure payment gateway integration.',
                'features'    => ['Product Management', 'Payment Gateway (SSLCommerz/Stripe)', 'Order Tracking', 'Inventory System', 'Customer Panel'],
                'price'       => '$1,000 - $3,000',
                'duration'    => '4-6 weeks',
            ],
            [
                'keywords'    => ['api', 'backend', 'server', 'rest', 'microservice', 'integration'],
                'name'        => 'API Development',
                'icon'        => '⚡',
                'description' => 'Robust and scalable RESTful API with complete documentation.',
                'features'    => ['RESTful API', 'Authentication (Sanctum/JWT)', 'API Documentation', 'Rate Limiting', 'Testing'],
                'price'       => '$300 - $1,000',
                'duration'    => '1-3 weeks',
            ],
            [
                'keywords'    => ['app', 'mobile', 'android', 'ios', 'flutter', 'react native'],
                'name'        => 'Mobile App Development',
                'icon'        => '📱',
                'description' => 'Cross-platform mobile application for both iOS and Android.',
                'features'    => ['iOS & Android', 'Push Notifications', 'Offline Support', 'Backend Integration', 'App Store Deployment'],
                'price'       => '$800 - $3,000',
                'duration'    => '4-8 weeks',
            ],
            [
                'keywords'    => ['design', 'ui', 'ux', 'figma', 'logo', 'branding', 'graphic'],
                'name'        => 'UI/UX Design',
                'icon'        => '🎨',
                'description' => 'Modern, clean, and user-friendly interface design.',
                'features'    => ['Wireframing', 'Prototyping', 'Responsive Design', 'Brand Identity', 'Design System'],
                'price'       => '$200 - $800',
                'duration'    => '1-2 weeks',
            ],
            [
                'keywords'    => ['seo', 'marketing', 'rank', 'google', 'traffic', 'optimization'],
                'name'        => 'SEO & Performance Optimization',
                'icon'        => '🚀',
                'description' => 'Boost your website ranking and performance on Google.',
                'features'    => ['On-Page SEO', 'Technical SEO', 'Speed Optimization', 'Sitemap & Robots.txt', 'Analytics Setup'],
                'price'       => '$150 - $500',
                'duration'    => '1-2 weeks',
            ],
        ];

        foreach ($definitions as $def) {
            foreach ($def['keywords'] as $keyword) {
                if (str_contains($message, $keyword)) {
                    $services[] = [
                        'name'        => $def['name'],
                        'icon'        => $def['icon'],
                        'description' => $def['description'],
                        'features'    => $def['features'],
                        'price'       => $def['price'],
                        'duration'    => $def['duration'],
                    ];
                    break; // একটা match হলেই এই service add হবে
                }
            }
        }

        // কোনো match না হলে default
        if (empty($services)) {
            $services[] = [
                'name'        => 'Custom Development',
                'icon'        => '💻',
                'description' => 'Let\'s discuss your project and build the perfect solution for you.',
                'features'    => ['Requirement Analysis', 'Custom Architecture', 'Scalable Solution', 'Ongoing Support', 'Flexible Timeline'],
                'price'       => 'Contact for Quote',
                'duration'    => 'Depends on project',
            ];
        }

        return $services;
    }
}
